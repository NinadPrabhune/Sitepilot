<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;

class CreateMissingPaymentCreditEntries extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:create-missing-payment-credits 
                            {--dry-run : Show what would be created without actually creating entries}
                            {--force : Force creation even if entries might duplicate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing payment credit entries for existing paid machinery payment requests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting migration: Create missing payment credit entries for existing paid requests');
        
        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No entries will be created');
        }
        
        // Get all payment requests that are marked as paid or locked (approved payments)
        $paidRequests = MachineryPaymentRequest::whereIn('status', ['paid', 'locked'])
            ->where(function($query) {
                $query->whereNotNull('paid_at')
                      ->orWhereNotNull('locked_at');
            })
            ->orderByRaw("CASE WHEN status = 'paid' THEN paid_at ELSE locked_at END")
            ->get();
            
        $this->info("Found {$paidRequests->count()} payment requests to process");
        
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        $progressBar = $this->output->createProgressBar($paidRequests->count());
        $progressBar->start();
        
        foreach ($paidRequests as $request) {
            $progressBar->advance();
            
            try {
                // Check if payment debit entry already exists for this payment request
                $existingEntry = MachineryLedger::where('reference_type', 'MachineryPaymentRequest')
                    ->where('reference_id', $request->id)
                    ->where('entry_type', 'payment_debit')
                    ->first();
                    
                if ($existingEntry) {
                    $skippedCount++;
                    continue;
                }
                
                if ($this->option('dry-run')) {
                    $this->line("\nWould create payment debit entry for request #{$request->id} - Amount: {$request->net_payable}");
                    $createdCount++;
                    continue;
                }
                
                // Get machinery information
                $machinery = Machinery::findOrFail($request->machinery_id);
                
                // Determine payment date (paid_at for paid status, locked_at for locked status)
                $paymentDate = $request->paid_at ?? $request->locked_at;
                
                // Calculate running balance at the time of payment
                $lastBalance = MachineryLedger::where('machinery_id', $request->machinery_id)
                    ->where('is_reversal', false)
                    ->where('date', '<=', $paymentDate->format('Y-m-d'))
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->value('running_balance') ?? 0;
                
                $runningBalance = $lastBalance - $request->net_payable;
                
                // Create the payment debit entry
                MachineryLedger::create([
                    'machinery_id' => $request->machinery_id,
                    'workspace_id' => $request->workspace_id,
                    'entry_direction' => 'debit',
                    'entry_type' => 'payment_debit',
                    'ledger_type' => $machinery->owned_by === 'owned' ? 'internal_cost' : 'payable',
                    'cost_category' => 'payment',
                    'reference_type' => 'MachineryPaymentRequest',
                    'reference_id' => $request->id,
                    'payment_request_id' => $request->id,
                    'amount' => $request->net_payable,
                    'running_balance' => $runningBalance,
                    'date' => $paymentDate->format('Y-m-d'),
                    'description' => "Payment #{$request->id} - {$machinery->name} (Migrated)",
                    'is_reversal' => false,
                    'metadata' => [
                        'payment_request_id' => $request->id,
                        'paid_by' => $request->paid_by,
                        'paid_at' => $request->paid_at?->toDateTimeString(),
                        'locked_at' => $request->locked_at->toDateTimeString(),
                        'migration' => true,
                        'migration_date' => now()->toDateTimeString(),
                    ],
                ]);
                
                $createdCount++;
                
            } catch (\Exception $e) {
                $this->error("\nFailed to create payment credit entry for request #{$request->id}: " . $e->getMessage());
                $errorCount++;
            }
        }
        
        $progressBar->finish();
        $this->newLine();
        
        $this->info('Migration completed:');
        $this->info("- Total paid requests: {$paidRequests->count()}");
        $this->info("- Created payment credit entries: {$createdCount}");
        $this->info("- Skipped (already exist): {$skippedCount}");
        $this->info("- Errors: {$errorCount}");
        
        if (!$this->option('dry-run') && $createdCount > 0) {
            $this->warn('Payment credit entries have been created. Check the machinery ledger to verify.');
        }
        
        return $errorCount === 0 ? 0 : 1;
    }
}
