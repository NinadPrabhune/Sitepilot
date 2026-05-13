<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Models\Machinery;

class CreateMissingPaymentCreditEntries extends Seeder
{
    /**
     * Run the database seeds to create missing payment credit entries
     * for existing paid payment requests.
     */
    public function run()
    {
        Log::info('Starting migration: Create missing payment credit entries for existing paid requests');
        
        // Get all payment requests that are marked as paid
        $paidRequests = MachineryPaymentRequest::where('status', 'paid')
            ->whereNotNull('paid_at')
            ->get();
            
        $createdCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        
        foreach ($paidRequests as $request) {
            try {
                // Check if payment credit entry already exists for this payment request
                $existingEntry = MachineryLedger::where('reference_type', 'MachineryPaymentRequest')
                    ->where('reference_id', $request->id)
                    ->where('entry_type', 'payment_credit')
                    ->first();
                    
                if ($existingEntry) {
                    Log::info("Payment credit entry already exists for request #{$request->id}");
                    $skippedCount++;
                    continue;
                }
                
                // Get machinery information
                $machinery = Machinery::findOrFail($request->machinery_id);
                
                // Calculate running balance at the time of payment
                $lastBalance = MachineryLedger::where('machinery_id', $request->machinery_id)
                    ->where('is_reversal', false)
                    ->where('date', '<=', $request->paid_at->format('Y-m-d'))
                    ->orderBy('date', 'desc')
                    ->orderBy('id', 'desc')
                    ->value('running_balance') ?? 0;
                
                $runningBalance = $lastBalance + $request->net_payable;
                
                // Create the payment credit entry
                MachineryLedger::create([
                    'machinery_id' => $request->machinery_id,
                    'workspace_id' => $request->workspace_id,
                    'entry_direction' => 'credit',
                    'entry_type' => 'payment_credit',
                    'ledger_type' => $machinery->owned_by === 'owned' ? 'internal_cost' : 'payable',
                    'cost_category' => 'payment',
                    'reference_type' => 'MachineryPaymentRequest',
                    'reference_id' => $request->id,
                    'payment_request_id' => $request->id,
                    'amount' => $request->net_payable,
                    'running_balance' => $runningBalance,
                    'date' => $request->paid_at->format('Y-m-d'),
                    'description' => "Payment #{$request->id} - {$machinery->name} (Migrated)",
                    'is_reversal' => false,
                    'metadata' => [
                        'payment_request_id' => $request->id,
                        'paid_by' => $request->paid_by,
                        'paid_at' => $request->paid_at->toDateTimeString(),
                        'migration' => true,
                        'migration_date' => now()->toDateTimeString(),
                    ],
                ]);
                
                Log::info("Created payment credit entry for request #{$request->id}", [
                    'machinery_id' => $request->machinery_id,
                    'amount' => $request->net_payable,
                    'running_balance' => $runningBalance,
                ]);
                
                $createdCount++;
                
            } catch (\Exception $e) {
                Log::error("Failed to create payment credit entry for request #{$request->id}", [
                    'error' => $e->getMessage(),
                    'payment_request_id' => $request->id,
                ]);
                $errorCount++;
            }
        }
        
        Log::info('Migration completed', [
            'total_paid_requests' => $paidRequests->count(),
            'created_entries' => $createdCount,
            'skipped_entries' => $skippedCount,
            'errors' => $errorCount,
        ]);
        
        echo "Migration completed:\n";
        echo "- Total paid requests: {$paidRequests->count()}\n";
        echo "- Created payment credit entries: {$createdCount}\n";
        echo "- Skipped (already exist): {$skippedCount}\n";
        echo "- Errors: {$errorCount}\n";
    }
}
