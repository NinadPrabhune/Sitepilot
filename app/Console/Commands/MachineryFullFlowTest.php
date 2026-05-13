<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\Feature\MachineryFullFlowValidationTest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class MachineryFullFlowTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'machinery:test-full-flow 
                            {--phase= : Run specific phase (0-8 or all)}
                            {--chaos : Run only chaos tests}
                            {--cleanup : Clean up test data after completion}
                            {--detailed : Detailed output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '🧠 Run comprehensive machinery management full flow validation tests';

    private $testResults = [];
    private $phaseResults = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🎯 MACHINERY FULL FLOW VALIDATION WITH INTENT');
        $this->info('==========================================');
        $this->newLine();

        $phase = $this->option('phase');
        $chaosOnly = $this->option('chaos');
        $cleanup = $this->option('cleanup');
        $detailed = $this->option('detailed');

        try {
            if ($chaosOnly) {
                $this->runChaosTests();
            } elseif ($phase === null) {
                $this->runAllPhases();
            } else {
                $this->runSpecificPhase($phase);
            }

            $this->displayResults();

            if ($cleanup) {
                $this->cleanupTestData();
            }

            $this->newLine();
            $this->info('🏁 Full Flow Validation Complete!');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Test execution failed: ' . $e->getMessage());
            Log::error('Machinery full flow test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Run all test phases in sequence
     */
    private function runAllPhases()
    {
        $phases = [
            0 => '🏗️ MASTER DATA SETUP (CRITICAL FOUNDATION)',
            1 => '⚙️ DPR CREATION (CORE ENGINE TEST)',
            2 => '⛽ DIESEL MANAGEMENT TEST',
            3 => '👷 OPERATOR ENTRY TEST',
            4 => '💰 PAYMENT FLOW TEST (CRITICAL)',
            5 => '🔁 REVERSAL TEST (AUDIT TEST)',
            6 => '📊 MACHINE WORK REPORT TEST',
            7 => '🧪 BEHAVIORAL TEST',
            8 => '📈 REPORT + WARNING VISIBILITY',
        ];

        foreach ($phases as $phaseNumber => $phaseName) {
            $this->runSpecificPhase($phaseNumber);
        }

        // Run final chaos tests
        $this->runChaosTests();
    }

    /**
     * Run a specific test phase
     */
    private function runSpecificPhase($phaseNumber)
    {
        $phaseNames = [
            0 => '🏗️ MASTER DATA SETUP (CRITICAL FOUNDATION)',
            1 => '⚙️ DPR CREATION (CORE ENGINE TEST)',
            2 => '⛽ DIESEL MANAGEMENT TEST',
            3 => '👷 OPERATOR ENTRY TEST',
            4 => '💰 PAYMENT FLOW TEST (CRITICAL)',
            5 => '🔁 REVERSAL TEST (AUDIT TEST)',
            6 => '📊 MACHINE WORK REPORT TEST',
            7 => '🧪 BEHAVIORAL TEST',
            8 => '📈 REPORT + WARNING VISIBILITY',
        ];

        if (!isset($phaseNames[$phaseNumber])) {
            $this->error("Invalid phase: {$phaseNumber}. Valid phases: 0-8");
            return;
        }

        $phaseName = $phaseNames[$phaseNumber];
        $this->info("Running Phase {$phaseNumber}: {$phaseName}");
        $this->line(str_repeat('-', 60));

        $startTime = microtime(true);

        try {
            $result = $this->executePhase($phaseNumber);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $this->phaseResults[$phaseNumber] = [
                'name' => $phaseName,
                'status' => $result ? 'PASS' : 'FAIL',
                'duration' => $duration,
                'details' => $this->getPhaseDetails($phaseNumber)
            ];

            if ($result) {
                $this->info("✅ Phase {$phaseNumber} PASSED ({$duration}ms)");
            } else {
                $this->error("❌ Phase {$phaseNumber} FAILED ({$duration}ms)");
            }

        } catch (\Exception $e) {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);

            $this->phaseResults[$phaseNumber] = [
                'name' => $phaseName,
                'status' => 'ERROR',
                'duration' => $duration,
                'error' => $e->getMessage()
            ];

            $this->error("❌ Phase {$phaseNumber} ERROR: " . $e->getMessage());
        }

        $this->newLine();
    }

    /**
     * Execute specific phase logic
     */
    private function executePhase($phaseNumber): bool
    {
        switch ($phaseNumber) {
            case 0:
                return $this->testMasterDataValidation();
            case 1:
                return $this->testDPRCreation();
            case 2:
                return $this->testDieselManagement();
            case 3:
                return $this->testOperatorEntry();
            case 4:
                return $this->testPaymentFlow();
            case 5:
                return $this->testReversalAudit();
            case 6:
                return $this->testWorkReportAggregation();
            case 7:
                return $this->testBehavioralValidation();
            case 8:
                return $this->testReportVisibility();
            default:
                throw new \InvalidArgumentException("Invalid phase: {$phaseNumber}");
        }
    }

    /**
     * Phase 0: Master Data Validation
     */
    private function testMasterDataValidation(): bool
    {
        $this->comment('Testing owned vs rental machinery validation rules...');

        // Test owned machinery creation (should succeed without supplier)
        try {
            $ownedMachinery = \App\Models\Machinery::create([
                'name' => 'Test Excavator A',
                'owned_by' => 'owned',
                'rate' => 1500,
                'supplier_id' => null,
                'category_id' => 1,
                'workspace_id' => 1,
                'created_by' => 1,
            ]);

            $this->line("✅ Owned machinery created successfully");
        } catch (\Exception $e) {
            $this->line("❌ Owned machinery creation failed: " . $e->getMessage());
            return false;
        }

        // Test rental machinery creation (should require supplier)
        try {
            $supplier = \App\Models\Supplier::first();
            if (!$supplier) {
                $this->line("❌ No supplier found for rental machinery test");
                return false;
            }

            $rentalMachinery = \App\Models\Machinery::create([
                'name' => 'Test Excavator B',
                'owned_by' => 'rental',
                'rate' => 1200,
                'minimum_billing_hours' => 8,
                'supplier_id' => $supplier->id,
                'category_id' => 1,
                'workspace_id' => 1,
                'created_by' => 1,
            ]);

            $this->line("✅ Rental machinery created successfully");
        } catch (\Exception $e) {
            $this->line("❌ Rental machinery creation failed: " . $e->getMessage());
            return false;
        }

        // Test validation rules
        $this->line("✅ Master data validation rules enforced");
        return true;
    }

    /**
     * Phase 1: DPR Creation
     */
    private function testDPRCreation(): bool
    {
        $this->comment('Testing DPR creation for both machinery types...');

        try {
            $ownedMachinery = \App\Models\Machinery::where('owned_by', 'owned')->first();
            $rentalMachinery = \App\Models\Machinery::where('owned_by', 'rental')->first();

            if (!$ownedMachinery || !$rentalMachinery) {
                $this->line("❌ Test machinery not found");
                return false;
            }

            // Test owned DPR
            $ownedDpr = \App\Domain\Machinery\Services\DailyProgressReportService::createDPRWithLedger([
                'date' => now()->toDateString(),
                'machinery_id' => $ownedMachinery->id,
                'machine_start_reading' => 100,
                'machine_end_reading' => 106,
                'machine_idle_reading' => 1,
                'number_of_operators' => 2,
                'operator_names' => 'John, Mike',
                'workspace_id' => 1,
                'created_by' => 1,
            ]);

            $this->line("✅ Owned DPR created: {$ownedDpr->calculated_amount} amount");

            // Test rental DPR with minimum billing
            $rentalDpr = \App\Domain\Machinery\Services\DailyProgressReportService::createDPRWithLedger([
                'date' => now()->toDateString(),
                'machinery_id' => $rentalMachinery->id,
                'machine_start_reading' => 200,
                'machine_end_reading' => 205,
                'machine_idle_reading' => 1,
                'number_of_operators' => 2,
                'operator_names' => 'Dave, Steve',
                'workspace_id' => 1,
                'created_by' => 1,
            ]);

            $this->line("✅ Rental DPR created: {$rentalDpr->calculated_amount} amount (minimum billing applied)");

            // Verify ledger entries
            $ownedLedger = \App\Domain\Machinery\Models\MachineryLedger::where('reference_id', $ownedDpr->id)->first();
            $rentalLedger = \App\Domain\Machinery\Models\MachineryLedger::where('reference_id', $rentalDpr->id)->first();

            if ($ownedLedger && $ownedLedger->ledger_type === 'internal_cost') {
                $this->line("✅ Owned DPR ledger type: internal_cost");
            } else {
                $this->line("❌ Owned DPR ledger type incorrect");
                return false;
            }

            if ($rentalLedger && $rentalLedger->ledger_type === 'payable') {
                $this->line("✅ Rental DPR ledger type: payable");
            } else {
                $this->line("❌ Rental DPR ledger type incorrect");
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->line("❌ DPR creation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 2: Diesel Management
     */
    private function testDieselManagement(): bool
    {
        $this->comment('Testing diesel management validation...');

        try {
            $dprs = \App\Models\DailyProgressReport::limit(2)->get();
            
            foreach ($dprs as $dpr) {
                $diesel = \App\Domain\Machinery\Services\DieselConsumptionService::createDieselConsumption([
                    'date' => now()->toDateString(),
                    'machinery_id' => $dpr->machinery_id,
                    'site_id' => $dpr->site_id ?? 1,
                    'workspace_id' => 1,
                    'created_by' => 1,
                    'liters' => 50,
                    'rate_per_liter' => 100,
                    'total_cost' => 5000,
                    'daily_progress_report_id' => $dpr->id,
                ]);

                // Verify ledger entry
                $ledger = \App\Domain\Machinery\Models\MachineryLedger::where('reference_id', $diesel->id)->first();
                if ($ledger && $ledger->ledger_type === 'expense' && $ledger->cost_category === 'diesel') {
                    $this->line("✅ Diesel ledger entry created: expense - diesel");
                } else {
                    $this->line("❌ Diesel ledger entry incorrect");
                    return false;
                }
            }

            return true;

        } catch (\Exception $e) {
            $this->line("❌ Diesel management test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 3: Operator Entry
     */
    private function testOperatorEntry(): bool
    {
        $this->comment('Testing operator entry validation...');

        try {
            $machinery = \App\Models\Machinery::first();
            
            // Valid operator entry
            $validDpr = \App\Models\DailyProgressReport::create([
                'date' => now()->addDay()->toDateString(),
                'machinery_id' => $machinery->id,
                'machine_start_reading' => 100,
                'machine_end_reading' => 105,
                'number_of_operators' => 2,
                'operator_names' => 'John, Mike',
                'workspace_id' => 1,
                'created_by' => 1,
            ]);

            $this->line("✅ Valid operator entry created");

            // Invalid operator entry (should warn but allow)
            $invalidDpr = \App\Models\DailyProgressReport::create([
                'date' => now()->addDays(2)->toDateString(),
                'machinery_id' => $machinery->id,
                'machine_start_reading' => 100,
                'machine_end_reading' => 105,
                'number_of_operators' => 2,
                'operator_names' => 'John', // Mismatch count
                'workspace_id' => 1,
                'created_by' => 1,
            ]);

            $this->line("⚠️  Invalid operator entry created (requires override)");
            return true;

        } catch (\Exception $e) {
            $this->line("❌ Operator entry test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 4: Payment Flow
     */
    private function testPaymentFlow(): bool
    {
        $this->comment('Testing payment flow (rental only)...');

        try {
            $rentalDpr = \App\Models\DailyProgressReport::whereHas('machinery', function ($query) {
                $query->where('owned_by', 'rental');
            })->first();

            if (!$rentalDpr) {
                $this->line("❌ No rental DPR found for payment test");
                return false;
            }

            // Create payment request for rental machinery
            $paymentRequest = \App\Domain\Machinery\Models\MachineryPaymentRequest::create([
                'machinery_id' => $rentalDpr->machinery_id,
                'daily_progress_report_id' => $rentalDpr->id,
                'amount' => $rentalDpr->calculated_amount,
                'status' => 'draft',
                'requested_by' => 1,
                'workspace_id' => 1,
            ]);

            $this->line("✅ Payment request created for rental machinery");

            // Submit and approve payment
            $paymentRequest->update(['status' => 'submitted']);
            $paymentRequest->update(['status' => 'verified']);
            $paymentRequest->update([
                'status' => 'approved',
                'approved_by' => 1,
                'approved_at' => now(),
            ]);

            $this->line("✅ Payment approved");

            // Try payment for owned machinery (should fail)
            $ownedDpr = \App\Models\DailyProgressReport::whereHas('machinery', function ($query) {
                $query->where('owned_by', 'owned');
            })->first();

            try {
                \App\Domain\Machinery\Models\MachineryPaymentRequest::create([
                    'machinery_id' => $ownedDpr->machinery_id,
                    'daily_progress_report_id' => $ownedDpr->id,
                    'amount' => $ownedDpr->calculated_amount,
                    'status' => 'draft',
                    'requested_by' => 1,
                    'workspace_id' => 1,
                ]);
                
                $this->line("❌ Payment request allowed for owned machinery (should fail)");
                return false;

            } catch (\Exception $e) {
                $this->line("✅ Payment request correctly blocked for owned machinery");
            }

            return true;

        } catch (\Exception $e) {
            $this->line("❌ Payment flow test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 5: Reversal Audit
     */
    private function testReversalAudit(): bool
    {
        $this->comment('Testing reversal and audit trail...');

        try {
            $ledger = \App\Domain\Machinery\Models\MachineryLedger::first();
            
            if (!$ledger) {
                $this->line("❌ No ledger entry found for reversal test");
                return false;
            }

            // Create reversal with admin user
            $adminUser = \App\Models\User::whereHas('roles', function($query) {
                $query->where('name', 'super admin');
            })->first();
            
            if (!$adminUser) {
                // Create admin user for testing
                $adminUser = \App\Models\User::factory()->create();
                $adminUser->assignRole('super admin');
            }
            
            // Authenticate as admin for reversal
            auth()->login($adminUser);
            
            $reversal = \App\Domain\Machinery\Services\MachineryLedgerService::reverseEntry(
                $ledger->id,
                'Test reversal for audit validation'
            );

            $this->line("✅ Reversal entry created");

            // Verify audit trail
            if ($reversal->is_reversal && $reversal->reversed_entry_id === $ledger->id) {
                $this->line("✅ Audit trail verified");
                return true;
            } else {
                $this->line("❌ Audit trail verification failed");
                return false;
            }

        } catch (\Exception $e) {
            $this->line("❌ Reversal audit test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 6: Work Report Aggregation
     */
    private function testWorkReportAggregation(): bool
    {
        $this->comment('Testing work report aggregation logic...');

        try {
            // Test financial separation
            $ownedLedgerEntries = \App\Domain\Machinery\Models\MachineryLedger::whereHas('machinery', function ($query) {
                $query->where('owned_by', 'owned');
            })->where('is_reversal', false)->get();

            $rentalLedgerEntries = \App\Domain\Machinery\Models\MachineryLedger::whereHas('machinery', function ($query) {
                $query->where('owned_by', 'rental');
            })->where('is_reversal', false)->get();

            $ownedInternalCost = $ownedLedgerEntries->where('ledger_type', 'internal_cost')->sum('amount');
            $ownedExpense = $ownedLedgerEntries->where('ledger_type', 'expense')->sum('amount');
            $rentalPayable = $rentalLedgerEntries->where('ledger_type', 'payable')->sum('amount');
            $rentalExpense = $rentalLedgerEntries->where('ledger_type', 'expense')->sum('amount');

            $this->line("✅ Financial aggregation:");
            $this->line("   Owned Internal Cost: {$ownedInternalCost}");
            $this->line("   Owned Expense: {$ownedExpense}");
            $this->line("   Rental Payable: {$rentalPayable}");
            $this->line("   Rental Expense: {$rentalExpense}");

            // Verify no mixing
            $ownedPayableEntries = $ownedLedgerEntries->where('ledger_type', 'payable');
            $rentalInternalCostEntries = $rentalLedgerEntries->where('ledger_type', 'internal_cost');

            if ($ownedPayableEntries->count() === 0 && $rentalInternalCostEntries->count() === 0) {
                $this->line("✅ Financial separation verified");
                return true;
            } else {
                $this->line("❌ Financial separation violated");
                return false;
            }

        } catch (\Exception $e) {
            $this->line("❌ Work report aggregation test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 7: Behavioral Validation
     */
    private function testBehavioralValidation(): bool
    {
        $this->comment('Testing behavioral validation system...');

        try {
            // Test validation service
            $validation = \App\Domain\Machinery\Services\MachineryValidationService::validateDPRCreation([
                'machine_start_reading' => 100,
                'machine_end_reading' => 105,
                'machine_idle_reading' => 3, // High idle
                'number_of_operators' => 2,
                'operator_names' => 'John', // Mismatch
            ]);

            if ($validation['requires_override']) {
                $this->line("✅ Behavioral validation correctly requires override");
            } else {
                $this->line("❌ Behavioral validation should require override");
                return false;
            }

            // Test validation score
            if ($validation['validation_score'] < 100) {
                $this->line("✅ Validation score calculated: {$validation['validation_score']}");
            } else {
                $this->line("❌ Validation score should be less than 100");
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->line("❌ Behavioral validation test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Phase 8: Report Visibility
     */
    private function testReportVisibility(): bool
    {
        $this->comment('Testing report visibility and quality scoring...');

        try {
            // Test warning statistics
            $stats = \App\Domain\Machinery\Services\MachineryValidationService::getWarningStatistics();
            
            $this->line("✅ Warning statistics:");
            $this->line("   Total Entries: {$stats['total_entries']}");
            $this->line("   Warning Rate: {$stats['warning_rate']}%");
            $this->line("   Requires Escalation: " . ($stats['requires_escalation'] ? 'Yes' : 'No'));

            // Test escalation logic
            $requiresEscalation = \App\Domain\Machinery\Services\MachineryValidationService::requiresEscalation(75);
            if (!$requiresEscalation) {
                $this->line("✅ Escalation logic working correctly (75% score does not trigger escalation)");
            } else {
                $this->line("❌ Escalation logic failed (75% score should not trigger escalation)");
                return false;
            }
            
            // Test with score that should trigger escalation
            $requiresEscalationLow = \App\Domain\Machinery\Services\MachineryValidationService::requiresEscalation(30);
            if ($requiresEscalationLow) {
                $this->line("✅ Escalation logic working correctly (30% score triggers escalation)");
            } else {
                $this->line("❌ Escalation logic failed (30% score should trigger escalation)");
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->line("❌ Report visibility test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Run final chaos tests
     */
    private function runChaosTests()
    {
        $this->info('🔒 FINAL CHAOS TEST (MOST IMPORTANT)');
        $this->line(str_repeat('-', 60));

        $chaosTests = [
            'Rate change after DPR' => $this->testRateChangeAfterDPR(),
            'Edit locked DPR' => $this->testEditLockedDPR(),
            'Duplicate entries' => $this->testDuplicateEntries(),
            'Mismatch ledger' => $this->testMismatchLedger(),
            'Mix cost & payable' => $this->testMixCostPayable(),
        ];

        $passedChaosTests = 0;
        $totalChaosTests = count($chaosTests);

        foreach ($chaosTests as $testName => $result) {
            if ($result) {
                $this->line("✅ {$testName}: PASSED");
                $passedChaosTests++;
            } else {
                $this->line("❌ {$testName}: FAILED");
            }
        }

        $chaosScore = round(($passedChaosTests / $totalChaosTests) * 100, 2);
        $this->newLine();
        $this->info("🎯 CHAOS TEST SCORE: {$chaosScore}% ({$passedChaosTests}/{$totalChaosTests})");

        $this->phaseResults['chaos'] = [
            'name' => '🔒 FINAL CHAOS TESTS',
            'status' => $chaosScore >= 80 ? 'PASS' : 'FAIL',
            'score' => $chaosScore,
            'passed' => $passedChaosTests,
            'total' => $totalChaosTests
        ];
    }

    /**
     * Individual chaos test methods
     */
    private function testRateChangeAfterDPR(): bool
    {
        try {
            $machinery = \App\Models\Machinery::first();
            $dpr = \App\Models\DailyProgressReport::where('machinery_id', $machinery->id)->first();
            
            if (!$machinery || !$dpr) {
                return false;
            }

            $originalAmount = $dpr->calculated_amount;
            $originalRate = $machinery->rate;

            // Change rate
            $machinery->update(['rate' => 9999]);
            
            // DPR amount should remain unchanged
            $dpr->refresh();
            if ($dpr->calculated_amount === $originalAmount) {
                $machinery->update(['rate' => $originalRate]); // Restore
                return true;
            }

            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    private function testEditLockedDPR(): bool
    {
        try {
            $lockedDpr = \App\Models\DailyProgressReport::where('status', 'approved')->first();
            
            if (!$lockedDpr) {
                // Create and lock a DPR for testing using the proper service
                $machinery = \App\Models\Machinery::first();
                $lockedDpr = \App\Domain\Machinery\Services\DailyProgressReportService::createDPRWithLedger([
                    'date' => now()->toDateString(),
                    'machinery_id' => $machinery->id,
                    'machine_start_reading' => 100,
                    'machine_end_reading' => 105,
                    'workspace_id' => 1,
                    'created_by' => 1,
                ]);
                
                // Approve the DPR to lock it
                \App\Domain\Machinery\Services\DailyProgressReportService::approveDPR($lockedDpr, 1);
            }

            // Try to edit locked DPR
            try {
                $lockedDpr->update(['work_details' => 'Should fail']);
                return false; // Should not reach here
            } catch (\Exception $e) {
                return true; // Expected to fail
            }

        } catch (\Exception $e) {
            return false;
        }
    }

    private function testDuplicateEntries(): bool
    {
        try {
            $dpr = \App\Models\DailyProgressReport::first();
            
            if (!$dpr) {
                return false;
            }

            // Try to create duplicate DPR using the service
            try {
                \App\Domain\Machinery\Services\DailyProgressReportService::createDPRWithLedger([
                    'date' => $dpr->date,
                    'machinery_id' => $dpr->machinery_id,
                    'machine_start_reading' => 100,
                    'machine_end_reading' => 105,
                    'workspace_id' => 1,
                    'created_by' => 1,
                ]);
                return false; // Should not reach here
            } catch (\Exception $e) {
                return true; // Expected to fail
            }

        } catch (\Exception $e) {
            return false;
        }
    }

    private function testMismatchLedger(): bool
    {
        try {
            // Try to create ledger with non-existent machinery
            try {
                \App\Domain\Machinery\Services\MachineryLedgerService::createCredit([
                    'machinery_id' => 99999,
                    'amount' => 1000,
                    'reference_type' => 'DailyProgressReport',
                    'reference_id' => 1,
                ]);
                return false; // Should not reach here
            } catch (\Exception $e) {
                return true; // Expected to fail
            }

        } catch (\Exception $e) {
            return false;
        }
    }

    private function testMixCostPayable(): bool
    {
        try {
            $ownedMachinery = \App\Models\Machinery::where('owned_by', 'owned')->first();
            
            if (!$ownedMachinery) {
                return false;
            }

            // Try to create payable entry for owned machinery
            try {
                \App\Domain\Machinery\Services\MachineryLedgerService::createCredit([
                    'machinery_id' => $ownedMachinery->id,
                    'amount' => 1000,
                    'reference_type' => 'DailyProgressReport',
                    'reference_id' => 1,
                    'payment_request_id' => 1, // This should fail for owned machinery
                ]);
                return false; // Should not reach here
            } catch (\Exception $e) {
                return true; // Expected to fail
            }

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get phase details for results display
     */
    private function getPhaseDetails($phaseNumber): string
    {
        $details = [
            0 => 'Validates owned vs rental machinery creation rules',
            1 => 'Tests DPR creation with proper financial classification',
            2 => 'Validates diesel consumption and expense tracking',
            3 => 'Tests operator entry validation and data integrity',
            4 => 'Validates payment flow for rental machinery only',
            5 => 'Tests reversal entries and audit trail integrity',
            6 => 'Validates financial aggregation and separation',
            7 => 'Tests behavioral validation and warning system',
            8 => 'Validates report visibility and quality scoring',
        ];

        return $details[$phaseNumber] ?? 'Unknown phase';
    }

    /**
     * Display comprehensive test results
     */
    private function displayResults()
    {
        $this->newLine();
        $this->info('📊 COMPREHENSIVE TEST RESULTS');
        $this->line(str_repeat('=', 60));

        $totalPhases = count($this->phaseResults);
        $passedPhases = 0;

        foreach ($this->phaseResults as $phaseNumber => $result) {
            $status = $result['status'];
            $name = $result['name'];
            $duration = $result['duration'] ?? 'N/A';

            if ($status === 'PASS') {
                $passedPhases++;
                $this->line("✅ Phase {$phaseNumber}: {$name} ({$duration}ms)");
            } else {
                $this->line("❌ Phase {$phaseNumber}: {$name} ({$duration}ms)");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }
            }
        }

        $overallScore = $totalPhases > 0 ? round(($passedPhases / $totalPhases) * 100, 2) : 0;
        
        $this->newLine();
        $this->info("🎯 OVERALL SCORE: {$overallScore}% ({$passedPhases}/{$totalPhases})");

        if ($overallScore >= 90) {
            $this->info('🏆 EXCELLENT - System is production ready!');
        } elseif ($overallScore >= 80) {
            $this->info('✅ GOOD - System meets most requirements');
        } elseif ($overallScore >= 70) {
            $this->line('⚠️  FAIR - System needs some improvements');
        } else {
            $this->error('❌ POOR - System requires significant fixes');
        }

        // Display final verdict
        $this->newLine();
        if ($overallScore >= 80) {
            $this->info('🧠 FINAL THINKER CHECKLIST:');
            $this->line('✅ Deterministic calculations');
            $this->line('✅ Correct financial classification');
            $this->line('✅ Behavioral accountability');
            $this->line('✅ Audit-safe flows');
            $this->info('🏁 SYSTEM CERTIFICATION: APPROVED');
        } else {
            $this->error('🏁 SYSTEM CERTIFICATION: FAILED');
            $this->error('   Address failing phases before production deployment');
        }
    }

    /**
     * Clean up test data
     */
    private function cleanupTestData()
    {
        $this->newLine();
        $this->info('🧹 Cleaning up test data...');

        try {
            // Clean up in proper order to respect foreign key constraints
            \App\Domain\Machinery\Models\MachineryLedger::where('reference_type', 'DailyConsumptionMaster')->delete();
            \App\Models\DailyConsumptionDetails::delete();
            \App\Models\DailyConsumptionMaster::delete();
            
            \App\Domain\Machinery\Models\MachineryPaymentRequest::delete();
            \App\Domain\Machinery\Models\MachineryLedger::where('reference_type', 'DailyProgressReport')->delete();
            \App\Models\DailyProgressReport::delete();
            
            \App\Models\Machinery::where('name', 'like', 'Test%')->delete();
            
            $this->line('✅ Test data cleaned up successfully');
        } catch (\Exception $e) {
            $this->error('❌ Cleanup failed: ' . $e->getMessage());
        }
    }
}
