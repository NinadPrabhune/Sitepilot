<?php

namespace Tests\Feature;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\MachineryLedger;
use App\Models\User;
use App\Services\MachineryFinancialFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Owned vs Rental Financial Flow Test
 * Validates proper financial treatment separation
 */
class OwnedVsRentalFinancialFlowTest extends TestCase
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
     * Test: Owned machinery creates internal cost ledger
     */
    public function test_owned_machinery_creates_internal_cost_ledger()
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

        // Verify financial flow
        $this->assertEquals('owned', $result['financial_flow']);
        $this->assertEquals('internal_cost', $result['ledger_type']);
        $this->assertFalse($result['payment_required']);
        $this->assertEquals(15000, $result['cost_components']['machine_cost']);

        // Verify ledger entry
        $ledger = MachineryLedger::where('dpr_id', $dpr->id)->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('internal_cost', $ledger->ledger_type);
        $this->assertEquals('credit', $ledger->entry_direction);
        $this->assertEquals(15000, $ledger->amount);
        $this->assertNull($ledger->payment_request_id); // No payment request for owned
    }

    /**
     * Test: Rental machinery creates payable ledger
     */
    public function test_rental_machinery_creates_payable_ledger()
    {
        $this->actingAs($this->adminUser);

        // Create DPR for rental machinery
        $dpr = DailyProgressReport::create([
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

        // Process financial flow
        $result = MachineryFinancialFlowService::processDprFinancials($dpr);

        // Verify financial flow
        $this->assertEquals('rental', $result['financial_flow']);
        $this->assertEquals('payable', $result['ledger_type']);
        $this->assertTrue($result['payment_required']);
        $this->assertEquals(18000, $result['cost_components']['machine_cost']);

        // Verify ledger entry
        $ledger = MachineryLedger::where('dpr_id', $dpr->id)->first();
        $this->assertNotNull($ledger);
        $this->assertEquals('payable', $ledger->ledger_type);
        $this->assertEquals('credit', $ledger->entry_direction);
        $this->assertEquals(18000, $ledger->amount);
        // Payment request would be created in separate workflow
    }

    /**
     * Test: Payment request allowed only for rental machinery
     */
    public function test_payment_request_allowed_only_for_rental()
    {
        // Owned machinery should not allow payment requests
        $this->assertFalse(
            MachineryFinancialFlowService::isPaymentRequestAllowed($this->ownedMachinery),
            'Owned machinery should not allow payment requests'
        );

        // Rental machinery should allow payment requests
        $this->assertTrue(
            MachineryFinancialFlowService::isPaymentRequestAllowed($this->rentalMachinery),
            'Rental machinery should allow payment requests'
        );
    }

    /**
     * Test: Financial treatment summary
     */
    public function test_financial_treatment_summary()
    {
        $ownedTreatment = MachineryFinancialFlowService::getFinancialTreatment($this->ownedMachinery);
        $rentalTreatment = MachineryFinancialFlowService::getFinancialTreatment($this->rentalMachinery);

        // Verify owned machinery treatment
        $this->assertEquals('owned', $ownedTreatment['owned_by']);
        $this->assertEquals('owned', $ownedTreatment['financial_flow']);
        $this->assertEquals('internal_cost', $ownedTreatment['ledger_type']);
        $this->assertFalse($ownedTreatment['payment_required']);
        $this->assertEquals('internal', $ownedTreatment['cost_tracking']);
        $this->assertFalse($ownedTreatment['supplier_involved']);

        // Verify rental machinery treatment
        $this->assertEquals('rental', $rentalTreatment['owned_by']);
        $this->assertEquals('rental', $rentalTreatment['financial_flow']);
        $this->assertEquals('payable', $rentalTreatment['ledger_type']);
        $this->assertTrue($rentalTreatment['payment_required']);
        $this->assertEquals('external', $rentalTreatment['cost_tracking']);
        $this->assertTrue($rentalTreatment['supplier_involved']);
    }

    /**
     * Test: Financial flow validation
     */
    public function test_financial_flow_validation()
    {
        $this->actingAs($this->adminUser);

        // Create DPR for owned machinery
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

        // Create correct ledger for owned machinery
        MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'ledger_type' => 'internal_cost',
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $ownedDpr->id,
            'dpr_id' => $ownedDpr->id,
            'amount' => 15000,
            'running_balance' => 15000,
            'date' => $ownedDpr->date,
        ]);

        // Validate owned machinery (should pass)
        $ownedIssues = MachineryFinancialFlowService::validateFinancialFlow($ownedDpr);
        $this->assertEmpty($ownedIssues, 'Owned machinery validation should pass');

        // Test wrong ledger type for owned machinery
        $wrongLedger = MachineryLedger::create([
            'machinery_id' => $this->ownedMachinery->id,
            'entry_direction' => 'credit',
            'entry_type' => 'reading',
            'ledger_type' => 'payable', // Wrong type for owned
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $ownedDpr->id,
            'dpr_id' => $ownedDpr->id,
            'amount' => 5000,
            'running_balance' => 20000,
            'date' => now()->addDay()->toDateString(),
        ]);

        // Validate should detect the issue
        $issues = MachineryFinancialFlowService::validateFinancialFlow($ownedDpr);
        $this->assertNotEmpty($issues, 'Should detect ledger type mismatch');
        
        $ledgerTypeIssue = collect($issues)->firstWhere('type', 'ledger_type_mismatch');
        $this->assertNotNull($ledgerTypeIssue);
        $this->assertStringContainsString('payable', $ledgerTypeIssue['message']);
    }

    /**
     * Test: Minimum billing applies only to rental machinery
     */
    public function test_minimum_billing_applies_only_to_rental()
    {
        $ownedTreatment = MachineryFinancialFlowService::getFinancialTreatment($this->ownedMachinery);
        $rentalTreatment = MachineryFinancialFlowService::getFinancialTreatment($this->rentalMachinery);

        // Owned machinery should not have minimum billing
        $this->assertFalse($ownedTreatment['minimum_billing_applies']);

        // Rental machinery should have minimum billing
        $this->assertTrue($rentalTreatment['minimum_billing_applies']);
    }

    /**
     * Test: Cost components separation
     */
    public function test_cost_components_separation()
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

        // Verify cost components structure
        $this->assertArrayHasKey('cost_components', $result);
        $this->assertArrayHasKey('machine_cost', $result['cost_components']);
        $this->assertArrayHasKey('diesel_cost', $result['cost_components']);
        $this->assertArrayHasKey('operator_cost', $result['cost_components']);

        // Machine cost should be calculated
        $this->assertEquals(15000, $result['cost_components']['machine_cost']);

        // Diesel and operator costs start at 0 (processed separately)
        $this->assertEquals(0, $result['cost_components']['diesel_cost']);
        $this->assertEquals(0, $result['cost_components']['operator_cost']);
    }
}
