<?php

namespace Database\Seeders;

use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryPaymentPeriod;
use App\Models\Machinery;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MachineryPaymentSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create test data
        $workspace = Workspace::first() ?? Workspace::factory()->create();
        $machinery = Machinery::first() ?? Machinery::factory()->create(['workspace_id' => $workspace->id]);
        $supplier = Supplier::first() ?? Supplier::factory()->create();
        $user = User::first() ?? User::factory()->create();
        
        // Create ledger entries for testing
        $this->createLedgerEntries($machinery->id, $workspace->id);
        
        // Create a sample payment request
        $this->createSamplePaymentRequest($machinery->id, $supplier->id, $workspace->id, $user->id);
        
        $this->command->info('Machinery payment system test data seeded successfully.');
    }
    
    private function createLedgerEntries(int $machineryId, int $workspaceId): void
    {
        $entries = [
            // Credit entries (payments due to machinery owner)
            [
                'date' => '2026-01-05',
                'entry_direction' => 'credit',
                'entry_type' => 'reading',
                'reference_type' => 'reading',
                'reference_id' => 1,
                'amount' => 5000.00,
                'description' => 'Monthly reading payment',
            ],
            [
                'date' => '2026-01-10',
                'entry_direction' => 'credit',
                'entry_type' => 'diesel',
                'reference_type' => 'diesel',
                'reference_id' => 1,
                'amount' => 2500.00,
                'description' => 'Diesel charge',
            ],
            [
                'date' => '2026-01-15',
                'entry_direction' => 'credit',
                'entry_type' => 'maintenance',
                'reference_type' => 'maintenance',
                'reference_id' => 1,
                'amount' => 1500.00,
                'description' => 'Maintenance charge',
            ],
            // Debit entries (deductions)
            [
                'date' => '2026-01-08',
                'entry_direction' => 'debit',
                'entry_type' => 'advance',
                'reference_type' => 'advance',
                'reference_id' => 1,
                'amount' => 1000.00,
                'description' => 'Advance payment deducted',
            ],
            [
                'date' => '2026-01-20',
                'entry_direction' => 'debit',
                'entry_type' => 'transfer',
                'reference_type' => 'transfer',
                'reference_id' => 1,
                'amount' => 500.00,
                'description' => 'Transfer deduction',
            ],
        ];
        
        $runningBalance = 0;
        
        foreach ($entries as $entry) {
            $amount = $entry['amount'];
            
            if ($entry['entry_direction'] === 'credit') {
                $runningBalance += $amount;
            } else {
                $runningBalance -= $amount;
            }
            
            MachineryLedger::create([
                'machinery_id' => $machineryId,
                'workspace_id' => $workspaceId,
                'entry_direction' => $entry['entry_direction'],
                'entry_type' => $entry['entry_type'],
                'reference_type' => $entry['reference_type'],
                'reference_id' => $entry['reference_id'],
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'date' => $entry['date'],
                'description' => $entry['description'],
                'metadata' => [
                    'created_by' => 'seeder',
                    'test_data' => true,
                ],
            ]);
        }
    }
    
    private function createSamplePaymentRequest(int $machineryId, int $supplierId, int $workspaceId, int $userId): void
    {
        // Calculate totals from ledger
        $credits = MachineryLedger::where('machinery_id', $machineryId)
            ->credits()
            ->sum('amount');
        
        $debits = MachineryLedger::where('machinery_id', $machineryId)
            ->debits()
            ->sum('amount');
        
        $netPayable = $credits - $debits;
        
        // Get ledger entry IDs for audit snapshot
        $ledgerEntries = MachineryLedger::where('machinery_id', $machineryId)
            ->where('is_reversal', false)
            ->get();
        
        $entriesHash = hash('sha256', json_encode($ledgerEntries->map(fn($e) => [
            'id' => $e->id,
            'date' => $e->date,
            'amount' => $e->amount,
            'entry_direction' => $e->entry_direction,
            'entry_type' => $e->entry_type,
        ])->toArray()));
        
        $paymentRequest = MachineryPaymentRequest::create([
            'machinery_id' => $machineryId,
            'supplier_id' => $supplierId,
            'workspace_id' => $workspaceId,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'credits' => $credits,
            'debits' => $debits,
            'net_payable' => $netPayable,
            'status' => 'draft',
            'audit_snapshot' => [
                'ledger_entry_ids' => $ledgerEntries->pluck('id')->toArray(),
                'entries_hash' => $entriesHash,
                'calculation_version' => 'v1',
                'calculation_timestamp' => now()->toDateTimeString(),
                'credits' => $credits,
                'debits' => $debits,
                'net_payable' => $netPayable,
                'entry_count' => $ledgerEntries->count(),
                'entry_details' => $ledgerEntries->map(fn($e) => [
                    'id' => $e->id,
                    'date' => $e->date,
                    'direction' => $e->entry_direction,
                    'type' => $e->entry_type,
                    'amount' => $e->amount,
                ])->toArray(),
            ],
            'idempotency_key' => bin2hex(random_bytes(32)),
            'requested_by' => $userId,
        ]);
        
        $this->command->info("Created sample payment request #{$paymentRequest->id} with net payable: {$netPayable}");
    }
}
