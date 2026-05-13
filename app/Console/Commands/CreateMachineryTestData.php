<?php

namespace App\Console\Commands;

use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Models\Supplier;
use App\Models\Workspace;
use Illuminate\Console\Command;

class CreateMachineryTestData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:create-test-data {--amount=100000 : Payable amount for test request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create test machinery payment request for Phase B1.6 operational testing';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🏗️  Creating Machinery Test Data (Phase B1.6)');
        $this->line('=====================================');

        try {
            // Get required data
            $workspace = Workspace::first();
            $supplier = Supplier::first();

            if (!$workspace) {
                $this->error('❌ No workspace found in database');
                return 1;
            }

            if (!$supplier) {
                $this->error('❌ No supplier found in database');
                return 1;
            }

            $amount = $this->option('amount');

            // Create test machinery payment request
            $request = MachineryPaymentRequest::create([
                'workspace_id' => $workspace->id,
                'supplier_id' => $supplier->id,
                'machinery_id' => null,
                'period_start' => now()->subMonth(),
                'period_end' => now(),
                'credits' => 0,
                'debits' => $amount,
                'net_payable' => $amount,
                'status' => 'locked',
                'locked_by' => 1,
                'locked_at' => now(),
            ]);

            $this->info("✅ Created test request #{$request->id}");
            $this->line("  Payable: ₹{$amount}");
            $this->line("  Status: {$request->status}");
            $this->line("  Supplier: {$supplier->name}");
            $workspaceName = $workspace->name ?? 'ID: ' . $workspace->id;
            $this->line("  Workspace: {$workspaceName}");

            $this->line("\n🧪 Ready for concurrent payment testing:");
            $this->line("  Test amount A: ₹" . ($amount * 0.6));
            $this->line("  Test amount B: ₹" . ($amount * 0.6));
            $this->line("  Expected: 1 succeeds, 1 fails");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to create test data: " . $e->getMessage());
            return 1;
        }
    }
}
