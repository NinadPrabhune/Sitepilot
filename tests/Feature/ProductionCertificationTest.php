<?php

namespace Tests\Feature;

use App\Domain\Machinery\Services\FinancialIntegrityWatchdog;
use App\Domain\Machinery\Services\ReadModelValidator;
use App\Domain\Machinery\Services\MachineryRateService;
use App\Domain\Machinery\Services\FinancialPeriodService;
use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\MachineryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Production Certification Test
 * 1 Week Simulation of real DPR system usage
 */
class ProductionCertificationTest extends TestCase
{
    use RefreshDatabase;

    private $watchdog;
    private $validator;
    private $rateService;
    private $periodService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->watchdog = new FinancialIntegrityWatchdog();
        $this->validator = new ReadModelValidator();
        $this->rateService = new MachineryRateService();
        $this->periodService = new FinancialPeriodService();
    }

    /**
     * Test: 1 Week Simulation
     */
    public function test_one_week_simulation()
    {
        // Setup test data
        $machinery = $this->createTestMachinery();
        $this->setupRateHistory($machinery);
        $this->setupFinancialPeriods();

        $results = [];
        
        // Simulate 7 days of operations
        for ($day = 1; $day <= 7; $day++) {
            $date = now()->subDays(7 - $day)->toDateString();
            
            // Create DPR for the day
            $dpr = $this->createDprForDay($machinery, $date, $day);
            
            // Simulate various operations based on day
            match($day) {
                1 => $this->simulateDay1Operations($dpr),
                2 => $this->simulateDay2Operations($dpr),
                3 => $this->simulateDay3Operations($dpr),
                4 => $this->simulateDay4Operations($dpr),
                5 => $this->simulateDay5Operations($dpr),
                6 => $this->simulateDay6Operations($dpr),
                7 => $this->simulateDay7Operations($dpr),
            };
            
            // Run daily integrity checks
            $dailyResults = $this->runDailyIntegrityChecks();
            $results["day_{$day}"] = $dailyResults;
        }

        // Final comprehensive validation
        $finalValidation = $this->runFinalValidation();
        
        // Assert all checks pass
        $this->assertSimulationResults($results, $finalValidation);
    }

    /**
     * Create test machinery
     */
    private function createTestMachinery(): Machinery
    {
        return Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * Setup rate history
     */
    private function setupRateHistory(Machinery $machinery): void
    {
        // Create rate history for the past week
        for ($i = 7; $i >= 1; $i--) {
            $date = now()->subDays($i)->toDateString();
            $rate = 1200.00 + ($i * 50); // Rates from 1250 to 1550
            
            $this->rateService->createRateHistory($machinery->id, $rate, $date);
        }
    }

    /**
     * Setup financial periods
     */
    private function setupFinancialPeriods(): void
    {
        // Create current month period (open)
        $this->periodService->createPeriod(
            'month',
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString()
        );
        
        // Create previous month period (locked)
        $this->periodService->createPeriod(
            'month',
            now()->subMonth()->startOfMonth()->toDateString(),
            now()->subMonth()->endOfMonth()->toDateString()
        );
        
        // Lock previous period
        $previousPeriod = DB::table('financial_periods')
            ->where('period_start', now()->subMonth()->startOfMonth()->toDateString())
            ->first();
        
        $this->periodService->lockPeriod($previousPeriod->id);
    }

    /**
     * Create DPR for specific day
     */
    private function createDprForDay(Machinery $machinery, string $date, int $day): DailyProgressReport
    {
        $startReading = ($day - 1) * 100;
        $endReading = $startReading + 10 + ($day % 3); // 10-12 hours
        $idleHours = $day % 2; // 0 or 1 hour
        
        return DailyProgressReport::create([
            'date' => $date,
            'machinery_id' => $machinery->id,
            'machine_start_reading' => $startReading,
            'machine_end_reading' => $endReading,
            'machine_idle_reading' => $idleHours,
            'rate_snapshot' => $this->rateService->getRateForDate($machinery->id, $date),
            'billable_hours' => ($endReading - $startReading) - $idleHours,
            'calculated_amount' => (($endReading - $startReading) - $idleHours) * $this->rateService->getRateForDate($machinery->id, $date),
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * Day 1: Normal DPR creation
     */
    private function simulateDay1Operations(DailyProgressReport $dpr): void
    {
        // Create corresponding ledger entry
        MachineryLedger::create([
            'machinery_id' => $dpr->machinery_id,
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
    }

    /**
     * Day 2: Rate change test
     */
    private function simulateDay2Operations(DailyProgressReport $dpr): void
    {
        // Create ledger entry
        $this->simulateDay1Operations($dpr);
        
        // Change rate for future (should not affect existing DPR)
        $newRate = $this->rateService->getCurrentRate($dpr->machinery_id) + 100;
        $this->rateService->createRateHistory($dpr->machinery_id, $newRate, now()->addDay()->toDateString());
        
        // Verify existing DPR rate snapshot unchanged
        $originalDpr = DailyProgressReport::find($dpr->id);
        $this->assertNotEquals($newRate, $originalDpr->rate_snapshot);
    }

    /**
     * Day 3: Minimum billing test
     */
    private function simulateDay3Operations(DailyProgressReport $dpr): void
    {
        // Create DPR with low hours (should trigger minimum billing)
        $dpr->update([
            'machine_end_reading' => $dpr->machine_start_reading + 5, // Only 5 hours
            'machine_idle_reading' => 1,
        ]);
        
        // Recalculate with minimum billing
        $workingHours = 5;
        $idleHours = 1;
        $billableHours = max(4, 8); // Minimum billing applied
        
        $dpr->update([
            'billable_hours' => $billableHours,
            'calculated_amount' => $billableHours * $dpr->rate_snapshot,
        ]);
        
        // Create ledger entry
        MachineryLedger::create([
            'machinery_id' => $dpr->machinery_id,
            'workspace_id' => 1,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'dpr_id' => $dpr->id,
            'amount' => $dpr->calculated_amount,
            'running_balance' => MachineryLedger::where('machinery_id', $dpr->machinery_id)
                ->where('is_reversal', false)
                ->sum('amount') + $dpr->calculated_amount,
            'date' => $dpr->date,
        ]);
    }

    /**
     * Day 4: Backdated entry test
     */
    private function simulateDay4Operations(DailyProgressReport $dpr): void
    {
        // Create ledger entry
        $this->simulateDay1Operations($dpr);
        
        // Create backdated DPR for 2 days ago
        $backdatedDate = now()->subDays(6)->toDateString();
        $historicalRate = $this->rateService->getRateForDate($dpr->machinery_id, $backdatedDate);
        
        $backdatedDpr = DailyProgressReport::create([
            'date' => $backdatedDate,
            'machinery_id' => $dpr->machinery_id,
            'machine_start_reading' => 50,
            'machine_end_reading' => 60,
            'rate_snapshot' => $historicalRate,
            'billable_hours' => 10,
            'calculated_amount' => 10 * $historicalRate,
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
        
        // Verify historical rate used
        $this->assertEquals($historicalRate, $backdatedDpr->rate_snapshot);
    }

    /**
     * Day 5: Overlap prevention test
     */
    private function simulateDay5Operations(DailyProgressReport $dpr): void
    {
        // Create ledger entry
        $this->simulateDay1Operations($dpr);
        
        // Try to create overlapping DPR (should be blocked by validation)
        $this->expectException(\Exception::class);
        
        DailyProgressReport::create([
            'date' => $dpr->date, // Same date
            'machinery_id' => $dpr->machinery_id, // Same machinery
            'machine_start_reading' => 200,
            'machine_end_reading' => 210,
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * Day 6: Period lock test
     */
    private function simulateDay6Operations(DailyProgressReport $dpr): void
    {
        // Create ledger entry
        $this->simulateDay1Operations($dpr);
        
        // Try to create DPR in locked period (should be blocked)
        $this->expectException(\Exception::class);
        
        $lockedPeriodDate = now()->subMonth()->toDateString();
        
        DailyProgressReport::create([
            'date' => $lockedPeriodDate,
            'machinery_id' => $dpr->machinery_id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'created_by' => 1,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * Day 7: Payment status test
     */
    private function simulateDay7Operations(DailyProgressReport $dpr): void
    {
        // Create ledger entry
        $this->simulateDay1Operations($dpr);
        
        // Simulate payment processing
        $dpr->update(['payment_status' => 'paid']);
        
        // Update corresponding ledger entries
        MachineryLedger::where('dpr_id', $dpr->id)
            ->update(['dpr_payment_status' => 'paid']);
        
        // Verify payment status consistency
        $ledgerStatus = MachineryLedger::where('dpr_id', $dpr->id)
            ->value('dpr_payment_status');
        
        $this->assertEquals('paid', $ledgerStatus);
    }

    /**
     * Run daily integrity checks
     */
    private function runDailyIntegrityChecks(): array
    {
        return [
            'financial_integrity' => $this->watchdog->runAllChecks(),
            'read_model_validation' => $this->validator->validateAllReadModels(),
        ];
    }

    /**
     * Run final comprehensive validation
     */
    private function runFinalValidation(): array
    {
        return [
            'financial_health' => $this->watchdog->getHealthSummary(),
            'read_model_health' => $this->validator->getValidationSummary(),
            'total_dprs' => DailyProgressReport::count(),
            'total_ledgers' => MachineryLedger::count(),
            'rate_history_entries' => DB::table('machinery_rate_history')->count(),
        ];
    }

    /**
     * Assert simulation results
     */
    private function assertSimulationResults(array $results, array $finalValidation): void
    {
        // Assert no integrity issues
        foreach ($results as $day => $dayResults) {
            $financialIssues = collect($dayResults['financial_integrity'])->sum('count');
            $readModelIssues = collect($dayResults['read_model_validation'])->sum('count');
            
            $this->assertEquals(0, $financialIssues, "Day {$day}: Financial integrity issues detected");
            $this->assertEquals(0, $readModelIssues, "Day {$day}: Read model validation issues detected");
        }
        
        // Assert final health is healthy
        $this->assertEquals('healthy', $finalValidation['financial_health']['overall_health']);
        $this->assertEquals('healthy', $finalValidation['read_model_health']['overall_health']);
        
        // Assert data consistency
        $this->assertGreaterThan(0, $finalValidation['total_dprs']);
        $this->assertGreaterThan(0, $finalValidation['total_ledgers']);
        $this->assertGreaterThan(0, $finalValidation['rate_history_entries']);
        
        // Assert DPR vs Ledger consistency
        $dprTotal = DailyProgressReport::sum('calculated_amount');
        $ledgerTotal = MachineryLedger::where('reference_type', 'DailyProgressReport')
            ->where('is_reversal', false)
            ->sum('amount');
        
        $this->assertEquals($dprTotal, $ledgerTotal, 'DPR total should match Ledger total');
    }
}
