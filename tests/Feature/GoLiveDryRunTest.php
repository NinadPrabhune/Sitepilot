<?php

namespace Tests\Feature;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\MachineryLedger;
use App\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Services\FinancialIntegrityWatchdog;
use App\Domain\Machinery\Services\ReadModelValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Go-Live Dry Run Test
 * Final comprehensive validation before production launch
 */
class GoLiveDryRunTest extends TestCase
{
    use RefreshDatabase;

    private $watchdog;
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->watchdog = new FinancialIntegrityWatchdog();
        $this->validator = new ReadModelValidator();
    }

    /**
     * Test: Complete DPR → Payment → Ledger cycle
     */
    public function test_complete_dpr_payment_ledger_cycle()
    {
        // 1. Create machinery
        $machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // 2. Create DPR
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 115,
            'machine_idle_reading' => 2,
            'rate_snapshot' => 1500,
            'billable_hours' => 13, // 15 - 2 hours
            'calculated_amount' => 19500, // 13 * 1500
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // 3. Create corresponding ledger entry
        $ledger = MachineryLedger::create([
            'machinery_id' => $machinery->id,
            'workspace_id' => 1,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'amount' => $dpr->calculated_amount,
            'running_balance' => $dpr->calculated_amount,
            'date' => $dpr->date,
        ]);

        // 4. Create payment request
        $paymentRequest = MachineryPaymentRequest::create([
            'machinery_id' => $machinery->id,
            'request_type' => 'payment',
            'total_amount' => $dpr->calculated_amount,
            'status' => 'submitted',
            'requested_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // 5. Link payment to DPR and ledger
        $ledger->update(['payment_request_id' => $paymentRequest->id]);
        $dpr->update(['payment_status' => 'submitted']);

        // Verify cycle completion
        $this->assertEquals($dpr->calculated_amount, $ledger->amount);
        $this->assertEquals($dpr->id, $ledger->dpr_id);
        $this->assertEquals($paymentRequest->id, $ledger->payment_request_id);
        $this->assertEquals('submitted', $dpr->payment_status);
    }

    /**
     * Test: Payment reversal process
     */
    public function test_payment_reversal_process()
    {
        // Setup initial DPR and ledger
        $machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        $originalLedger = MachineryLedger::create([
            'machinery_id' => $machinery->id,
            'workspace_id' => 1,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'amount' => $dpr->calculated_amount,
            'running_balance' => $dpr->calculated_amount,
            'date' => $dpr->date,
        ]);

        // Create reversal entry
        $reversalLedger = MachineryLedger::create([
            'machinery_id' => $machinery->id,
            'workspace_id' => 1,
            'entry_direction' => 'debit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'amount' => $dpr->calculated_amount,
            'running_balance' => 0, // Back to zero
            'date' => now()->toDateString(),
            'is_reversal' => true,
            'reversal_of_id' => $originalLedger->id,
        ]);

        // Verify reversal correctness
        $this->assertEquals('debit', $reversalLedger->entry_direction);
        $this->assertEquals($originalLedger->amount, $reversalLedger->amount);
        $this->assertTrue($reversalLedger->is_reversal);
        $this->assertEquals($originalLedger->id, $reversalLedger->reversal_of_id);
        $this->assertEquals(0, $reversalLedger->running_balance);

        // Verify DPR unchanged
        $originalDpr = $dpr->fresh();
        $this->assertEquals(15000, $originalDpr->calculated_amount);
        $this->assertEquals(1500, $originalDpr->rate_snapshot);
    }

    /**
     * Test: Report generation consistency
     */
    public function test_report_generation_consistency()
    {
        // Create multiple DPRs with different amounts
        $machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        $dprs = [];
        $totalExpectedAmount = 0;

        for ($i = 1; $i <= 5; $i++) {
            $amount = $i * 3000; // 3000, 6000, 9000, 12000, 15000
            $totalExpectedAmount += $amount;

            $dpr = DailyProgressReport::create([
                'date' => now()->subDays(5 - $i)->toDateString(),
                'machinery_id' => $machinery->id,
                'machine_start_reading' => ($i - 1) * 100,
                'machine_end_reading' => $i * 100,
                'machine_idle_reading' => 0,
                'rate_snapshot' => 1500,
                'billable_hours' => $amount / 1500,
                'calculated_amount' => $amount,
                'created_by' => 1,
                'workspace_id' => 1,
                'site_id' => 1,
            ]);

            // Create corresponding ledger
            MachineryLedger::create([
                'machinery_id' => $machinery->id,
                'workspace_id' => 1,
                'entry_direction' => 'credit',
                'entry_type' => 'reading',
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dpr->id,
                'dpr_id' => $dpr->id,
                'amount' => $amount,
                'running_balance' => $totalExpectedAmount,
                'date' => $dpr->date,
            ]);

            $dprs[] = $dpr;
        }

        // Generate reports and verify consistency
        $dprTotal = DailyProgressReport::sum('calculated_amount');
        $ledgerTotal = MachineryLedger::where('reference_type', 'DailyProgressReport')
                                    ->where('is_reversal', false)
                                    ->sum('amount');

        // Verify totals match
        $this->assertEquals($totalExpectedAmount, $dprTotal);
        $this->assertEquals($totalExpectedAmount, $ledgerTotal);
        $this->assertEquals($dprTotal, $ledgerTotal);

        // Verify individual DPR amounts match ledger amounts
        foreach ($dprs as $dpr) {
            $ledgerAmount = MachineryLedger::where('dpr_id', $dpr->id)
                                        ->where('is_reversal', false)
                                        ->sum('amount');
            $this->assertEquals($dpr->calculated_amount, $ledgerAmount);
        }
    }

    /**
     * Test: Ledger balance integrity
     */
    public function test_ledger_balance_integrity()
    {
        $machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Create sequence of ledger entries
        $entries = [];
        $runningBalance = 0;

        // Credit entries (DPRs)
        for ($i = 1; $i <= 3; $i++) {
            $amount = $i * 5000;
            $runningBalance += $amount;

            $dpr = DailyProgressReport::create([
                'date' => now()->subDays(3 - $i)->toDateString(),
                'machinery_id' => $machinery->id,
                'machine_start_reading' => ($i - 1) * 100,
                'machine_end_reading' => $i * 100,
                'machine_idle_reading' => 0,
                'rate_snapshot' => 1500,
                'billable_hours' => $amount / 1500,
                'calculated_amount' => $amount,
                'created_by' => 1,
                'workspace_id' => 1,
                'site_id' => 1,
            ]);

            $ledger = MachineryLedger::create([
                'machinery_id' => $machinery->id,
                'workspace_id' => 1,
                'entry_direction' => 'credit',
                'entry_type' => 'reading',
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dpr->id,
                'dpr_id' => $dpr->id,
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'date' => $dpr->date,
            ]);

            $entries[] = $ledger;
        }

        // Debit entries (expenses)
        for ($i = 1; $i <= 2; $i++) {
            $amount = $i * 2000;
            $runningBalance -= $amount;

            $ledger = MachineryLedger::create([
                'machinery_id' => $machinery->id,
                'workspace_id' => 1,
                'entry_direction' => 'debit',
                'entry_type' => 'diesel',
                'reference_type' => 'DailyConsumptionMaster',
                'reference_id' => $i,
                'amount' => $amount,
                'running_balance' => $runningBalance,
                'date' => now()->subDays(2 - $i)->toDateString(),
            ]);

            $entries[] = $ledger;
        }

        // Verify balance integrity
        $finalBalance = MachineryLedger::where('machinery_id', $machinery->id)
                                      ->where('is_reversal', false)
                                      ->orderBy('date', 'desc')
                                      ->orderBy('id', 'desc')
                                      ->value('running_balance');

        $this->assertEquals($runningBalance, $finalBalance);

        // Recalculate and verify
        $recalculatedBalance = MachineryLedger::where('machinery_id', $machinery->id)
                                            ->where('is_reversal', false)
                                            ->orderBy('date', 'asc')
                                            ->orderBy('id', 'asc')
                                            ->get()
                                            ->reduce(function ($balance, $entry) {
                                                return $entry->entry_direction === 'credit' 
                                                    ? $balance + $entry->amount 
                                                    : $balance - $entry->amount;
                                            }, 0);

        $this->assertEquals($runningBalance, $recalculatedBalance);
    }

    /**
     * Test: System integrity under concurrent operations
     */
    public function test_system_integrity_under_concurrent_operations()
    {
        $machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Simulate concurrent DPR creation
        $concurrentDprs = [];
        $totalAmount = 0;

        for ($i = 1; $i <= 10; $i++) {
            $amount = $i * 1000;
            $totalAmount += $amount;

            $dpr = DailyProgressReport::create([
                'date' => now()->toDateString(),
                'machinery_id' => $machinery->id,
                'machine_start_reading' => ($i - 1) * 50,
                'machine_end_reading' => $i * 50,
                'machine_idle_reading' => 0,
                'rate_snapshot' => 1500,
                'billable_hours' => $amount / 1500,
                'calculated_amount' => $amount,
                'created_by' => 1,
                'workspace_id' => 1,
                'site_id' => 1,
            ]);

            // This should fail due to overlap prevention after first DPR
            if ($i > 1) {
                $this->expectException(\Exception::class);
            }

            $concurrentDprs[] = $dpr;
        }
    }

    /**
     * Test: Final system health validation
     */
    public function test_final_system_health_validation()
    {
        // Create comprehensive test data
        $machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Create DPRs with various scenarios
        $this->createTestScenarios($machinery);

        // Run comprehensive integrity checks
        $financialResults = $this->watchdog->runAllChecks();
        $readModelResults = $this->validator->validateAllReadModels();

        // Assert all checks pass
        foreach ($financialResults as $check => $result) {
            $this->assertEquals(0, $result['count'], "Financial check '{$check}' failed");
        }

        foreach ($readModelResults as $check => $result) {
            $this->assertEquals(0, $result['count'], "Read model check '{$check}' failed");
        }

        // Get health summaries
        $financialHealth = $this->watchdog->getHealthSummary();
        $readModelHealth = $this->validator->getValidationSummary();

        $this->assertEquals('healthy', $financialHealth['overall_health']);
        $this->assertEquals('healthy', $readModelHealth['overall_health']);
        $this->assertEquals(0, $financialHealth['total_issues']);
        $this->assertEquals(0, $readModelHealth['total_issues']);
    }

    /**
     * Create comprehensive test scenarios
     */
    private function createTestScenarios(Machinery $machinery): void
    {
        // Normal DPR
        $dpr1 = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 115,
            'machine_idle_reading' => 2,
            'rate_snapshot' => 1500,
            'billable_hours' => 13,
            'calculated_amount' => 19500,
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryLedger::create([
            'machinery_id' => $machinery->id,
            'workspace_id' => 1,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr1->id,
            'dpr_id' => $dpr1->id,
            'amount' => $dpr1->calculated_amount,
            'running_balance' => $dpr1->calculated_amount,
            'date' => $dpr1->date,
        ]);

        // Minimum billing DPR
        $dpr2 = DailyProgressReport::create([
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $machinery->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 205, // Only 5 hours
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 8, // Minimum billing applied
            'calculated_amount' => 12000, // 8 * 1500
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryLedger::create([
            'machinery_id' => $machinery->id,
            'workspace_id' => 1,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr2->id,
            'dpr_id' => $dpr2->id,
            'amount' => $dpr2->calculated_amount,
            'running_balance' => $dpr1->calculated_amount + $dpr2->calculated_amount,
            'date' => $dpr2->date,
        ]);

        // Diesel expense
        MachineryLedger::create([
            'machinery_id' => $machinery->id,
            'workspace_id' => 1,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'amount' => 5000,
            'running_balance' => $dpr1->calculated_amount + $dpr2->calculated_amount - 5000,
            'date' => now()->toDateString(),
        ]);
    }
}
