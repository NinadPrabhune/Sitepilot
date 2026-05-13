<?php

namespace Tests\Feature;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\MachineryLedger;
use App\Models\User;
use App\Services\CostAccountingValidationService;
use App\Services\MachineryOwnershipLockService;
use App\Services\MachineryFinancialFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cost Accounting Integrity Test
 * Final validation of bulletproof cost accounting implementation
 */
class CostAccountingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $ownedMachinery;
    private $rentalMachinery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Create owned machinery
        $this->ownedMachinery = Machinery::create([
            'name' => 'Owned Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'owned',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Create rental machinery
        $this->rentalMachinery = Machinery::create([
            'name' => 'Rental Excavator',
            'rate' => 1800.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * Test: Cost category classification prevents double counting
     */
    public function test_cost_category_classification_prevents_double_counting()
    {
        $this->actingAs($this->adminUser);

        // Create DPR for owned machinery
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Process financial flow
        $result = MachineryFinancialFlowService::processDprFinancials($dpr);

        // Verify cost category is 'machine' for DPR
        $ledger = MachineryLedger::where('dpr_id', $dpr->id)->first();
        $this->assertEquals('machine', $ledger->cost_category);
        $this->assertEquals('internal_cost', $ledger->ledger_type);

        // Add diesel expense (separate category)
        $dieselLedger = MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'ledger_type' => 'expense',
            'cost_category' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'amount' => 5000,
            'running_balance' => 10000, // 15000 - 5000
            'date' => $dpr->date,
        ]);

        // Verify cost separation
        $machineCost = MachineryLedger::where('dpr_id', $dpr->id)
                                    ->where('cost_category', 'machine')
                                    ->sum('amount');
        $dieselCost = MachineryLedger::where('machinery_id', $this->ownedMachinery->id)
                                    ->where('cost_category', 'diesel')
                                    ->sum('amount');

        $this->assertEquals(15000, $machineCost);
        $this->assertEquals(5000, $dieselCost);
        $this->assertNotEquals($machineCost, $dieselCost); // Different costs
    }

    /**
     * Test: Cost vs payable separation validation
     */
    public function test_cost_vs_payable_separation_validation()
    {
        $this->actingAs($this->adminUser);

        // Create owned machinery DPR (internal cost)
        $ownedDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryFinancialFlowService::processDprFinancials($ownedDpr);

        // Create rental machinery DPR (payable)
        $rentalDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->rentalMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1800,
            'billable_hours' => 10,
            'calculated_amount' => 18000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryFinancialFlowService::processDprFinancials($rentalDpr);

        // Add expense for diesel
        MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'ledger_type' => 'expense',
            'cost_category' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'amount' => 5000,
            'running_balance' => 10000,
            'date' => now()->toDateString(),
        ]);

        // Validate cost/payable separation
        $validation = CostAccountingValidationService::validateCostPayableSeparation();

        $this->assertTrue($validation['valid'], 'Cost/payable separation should be valid');
        $this->assertEquals(15000, $validation['summary']['internal_cost_total']);
        $this->assertEquals(5000, $validation['summary']['expense_total']);
        $this->assertEquals(18000, $validation['summary']['payable_total']);
        $this->assertEquals(20000, $validation['summary']['total_project_cost']); // 15000 + 5000
        $this->assertNotEquals(20000, $validation['summary']['payable_total']); // Should not equal
    }

    /**
     * Test: Machinery ownership lock prevents changes
     */
    public function test_machinery_ownership_lock_prevents_changes()
    {
        $this->actingAs($this->adminUser);

        // Initially should be able to change ownership
        $this->assertTrue(
            MachineryOwnershipLockService::canChangeOwnership($this->ownedMachinery->id),
            'Should be able to change ownership initially'
        );

        // Create DPR for machinery
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Process financial flow (should lock ownership)
        MachineryFinancialFlowService::processDprFinancials($dpr);

        // Lock ownership after DPR creation
        MachineryOwnershipLockService::lockOwnership($this->ownedMachinery->id, $this->adminUser->id);

        // Now should NOT be able to change ownership
        $this->assertFalse(
            MachineryOwnershipLockService::canChangeOwnership($this->ownedMachinery->id),
            'Should NOT be able to change ownership after DPR creation'
        );

        // Verify lock status
        $lockStatus = MachineryOwnershipLockService::getLockStatus($this->ownedMachinery->id);
        $this->assertTrue($lockStatus['ownership_locked']);
        $this->assertEquals('owned', $lockStatus['current_ownership']);
        $this->assertEquals(1, $lockStatus['dpr_count']);
        $this->assertFalse($lockStatus['can_change_ownership']);

        // Attempt to change ownership should fail
        $this->expectException(\Exception::class);
        MachineryOwnershipLockService::changeOwnership($this->ownedMachinery->id, 'rental', $this->adminUser->id);
    }

    /**
     * Test: Double counting prevention
     */
    public function test_double_counting_prevention()
    {
        $this->actingAs($this->adminUser);

        // Create DPR and process financial flow
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryFinancialFlowService::processDprFinancials($dpr);

        // Add separate diesel expense
        MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'ledger_type' => 'expense',
            'cost_category' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'amount' => 5000,
            'running_balance' => 10000,
            'date' => now()->toDateString(),
        ]);

        // Check for double counting scenarios
        $scenarios = CostAccountingValidationService::checkDoubleCountingScenarios();

        $this->assertEquals(0, $scenarios['total_risk_scenarios'], 'Should have no double counting scenarios');
        $this->assertEquals(0, $scenarios['high_risk_count'], 'Should have no high-risk scenarios');

        // Verify cost breakdown is clean
        $report = CostAccountingValidationService::generateCostAccountingReport();
        
        $machineCosts = $report['cost_breakdown']->where('cost_category', 'machine');
        $dieselCosts = $report['cost_breakdown']->where('cost_category', 'diesel');

        $this->assertEquals(15000, $machineCosts->sum('total_amount'));
        $this->assertEquals(5000, $dieselCosts->sum('total_amount'));
    }

    /**
     * Test: Cost component integrity
     */
    public function test_cost_component_integrity()
    {
        $this->actingAs($this->adminUser);

        // Create DPR with exact amount
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Process financial flow
        MachineryFinancialFlowService::processDprFinancials($dpr);

        // Validate cost component integrity
        $integrity = CostAccountingValidationService::validateCostComponentIntegrity();

        $this->assertTrue($integrity['valid'], 'Cost component integrity should be valid');
        $this->assertEquals(0, $integrity['summary']['mismatches_found']);
        $this->assertEquals(1, $integrity['summary']['dpr_machine_costs_checked']);
    }

    /**
     * Test: Complete cost accounting scenario
     */
    public function test_complete_cost_accounting_scenario()
    {
        $this->actingAs($this->adminUser);

        // Scenario: Mixed owned and rental machinery with various costs
        
        // 1. Owned machinery DPR (internal cost)
        $ownedDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryFinancialFlowService::processDprFinancials($ownedDpr);
        MachineryOwnershipLockService::lockOwnership($this->ownedMachinery->id, $this->adminUser->id);

        // 2. Rental machinery DPR (payable)
        $rentalDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->rentalMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1800,
            'billable_hours' => 10,
            'calculated_amount' => 18000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        MachineryFinancialFlowService::processDprFinancials($rentalDpr);
        MachineryOwnershipLockService::lockOwnership($this->rentalMachinery->id, $this->adminUser->id);

        // 3. Add diesel expense
        MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'ledger_type' => 'expense',
            'cost_category' => 'diesel',
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'amount' => 5000,
            'running_balance' => 10000,
            'date' => now()->toDateString(),
        ]);

        // 4. Add maintenance expense
        MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'debit',
            'entry_type' => 'maintenance',
            'ledger_type' => 'expense',
            'cost_category' => 'maintenance',
            'reference_type' => 'MaintenanceRecord',
            'reference_id' => 1,
            'amount' => 2000,
            'running_balance' => 8000,
            'date' => now()->toDateString(),
        ]);

        // Generate comprehensive report
        $report = CostAccountingValidationService::generateCostAccountingReport();

        // Verify expected totals
        $this->assertEquals(15000, $report['validation']['summary']['internal_cost_total']);
        $this->assertEquals(7000, $report['validation']['summary']['expense_total']); // 5000 + 2000
        $this->assertEquals(18000, $report['validation']['summary']['payable_total']);
        $this->assertEquals(22000, $report['validation']['summary']['total_project_cost']); // 15000 + 7000

        // Verify no cost/payable mixing
        $this->assertNotEquals(22000, $report['validation']['summary']['payable_total']);
        $this->assertTrue($report['validation']['valid']);

        // Verify cost breakdown categories
        $machineCost = $report['cost_breakdown']->where('cost_category', 'machine')->sum('total_amount');
        $dieselCost = $report['cost_breakdown']->where('cost_category', 'diesel')->sum('total_amount');
        $maintenanceCost = $report['cost_breakdown']->where('cost_category', 'maintenance')->sum('total_amount');

        $this->assertEquals(15000, $machineCost);
        $this->assertEquals(5000, $dieselCost);
        $this->assertEquals(2000, $maintenanceCost);

        // Final validation: no double counting scenarios
        $scenarios = CostAccountingValidationService::checkDoubleCountingScenarios();
        $this->assertEquals(0, $scenarios['total_risk_scenarios']);
    }
}
