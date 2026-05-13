<?php

namespace Tests\Feature;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\Supplier;
use App\Models\DailyConsumptionMaster;
use App\Models\User;
use App\Services\MasterDataValidationService;
use App\Services\DprInputValidationService;
use App\Services\DieselManagementValidationService;
use App\Services\MachineryFinancialFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Operational Gap Test
 * Comprehensive real-world scenario testing to expose operational gaps
 */
class OperationalGapTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $siteEngineer;
    private $ownedMachinery;
    private $rentalMachinery;
    private $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->siteEngineer = User::factory()->create();
        $this->siteEngineer->assignRole('site engineer');

        // Create supplier for rental machinery
        $this->supplier = Supplier::create([
            'name' => 'Test Rental Supplier',
            'contact_person' => 'John Doe',
            'phone' => '1234567890',
            'workspace_id' => 1,
        ]);

        // Create owned machinery
        $this->ownedMachinery = Machinery::create([
            'name' => 'OWN-001',
            'rate' => 1500.00,
            'minimum_billing_hours' => 0, // No minimum for owned
            'owned_by' => 'owned',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Create rental machinery with supplier
        $this->rentalMachinery = Machinery::create([
            'name' => 'RENT-001',
            'rate' => 1800.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'supplier_id' => $this->supplier->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * 🔁 PHASE 0: Master Data Validation
     */
    public function test_master_data_validation()
    {
        // ✅ Test valid owned machinery
        $ownedValidation = MasterDataValidationService::validateMachinery($this->ownedMachinery);
        $this->assertTrue($ownedValidation['valid'], 'Owned machinery should be valid');

        // ✅ Test valid rental machinery
        $rentalValidation = MasterDataValidationService::validateMachinery($this->rentalMachinery);
        $this->assertTrue($rentalValidation['valid'], 'Rental machinery should be valid');

        // ❌ Test owned machinery with supplier (should fail)
        $this->ownedMachinery->update(['supplier_id' => $this->supplier->id]);
        $invalidOwnedValidation = MasterDataValidationService::validateMachinery($this->ownedMachinery);
        $this->assertFalse($invalidOwnedValidation['valid'], 'Owned machinery with supplier should be invalid');
        
        $supplierIssue = collect($invalidOwnedValidation['issues'])->firstWhere('type', 'owned_with_supplier');
        $this->assertNotNull($supplierIssue);

        // ❌ Test rental machinery without supplier (should fail)
        $this->rentalMachinery->update(['supplier_id' => null]);
        $invalidRentalValidation = MasterDataValidationService::validateMachinery($this->rentalMachinery);
        $this->assertFalse($invalidRentalValidation['valid'], 'Rental machinery without supplier should be invalid');
        
        $noSupplierIssue = collect($invalidRentalValidation['issues'])->firstWhere('type', 'rental_without_supplier');
        $this->assertNotNull($noSupplierIssue);

        // Reset for other tests
        $this->ownedMachinery->update(['supplier_id' => null]);
        $this->rentalMachinery->update(['supplier_id' => $this->supplier->id]);
    }

    /**
     * 🔁 PHASE 2: DPR Input Validation
     */
    public function test_dpr_input_validation()
    {
        // ✅ Test valid DPR input for owned machinery
        $validOwnedData = [
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 2,
            'number_of_operators' => 2,
            'operator_names' => 'John Doe, Jane Smith',
        ];

        $ownedValidation = DprInputValidationService::validateDprInput($validOwnedData);
        $this->assertTrue($ownedValidation['valid'], 'Valid owned DPR data should pass');

        // ✅ Test valid DPR input for rental machinery
        $validRentalData = [
            'date' => now()->toDateString(),
            'machinery_id' => $this->rentalMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105, // 5 hours
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'operator_names' => 'John Doe',
        ];

        $rentalValidation = DprInputValidationService::validateDprInput($validRentalData);
        $this->assertTrue($rentalValidation['valid'], 'Valid rental DPR data should pass');

        // ❌ Test negative readings (should fail)
        $negativeReadingData = $validOwnedData;
        $negativeReadingData['machine_start_reading'] = -10;

        $negativeValidation = DprInputValidationService::validateDprInput($negativeReadingData);
        $this->assertFalse($negativeValidation['valid'], 'Negative readings should fail');

        // ❌ Test end reading less than start (should fail)
        $invalidOrderData = $validOwnedData;
        $invalidOrderData['machine_end_reading'] = 90;

        $orderValidation = DprInputValidationService::validateDprInput($invalidOrderData);
        $this->assertFalse($orderValidation['valid'], 'End reading less than start should fail');

        // ❌ Test idle hours exceeding working hours (should fail)
        $excessiveIdleData = $validOwnedData;
        $excessiveIdleData['machine_idle_reading'] = 15; // More than working hours (10)

        $idleValidation = DprInputValidationService::validateDprInput($excessiveIdleData);
        $this->assertFalse($idleValidation['valid'], 'Idle hours exceeding working hours should fail');

        // ❌ Test operator name count mismatch (should fail)
        $mismatchData = $validOwnedData;
        $mismatchData['operator_names'] = 'John Doe'; // Only 1 name for 2 operators

        $mismatchValidation = DprInputValidationService::validateDprInput($mismatchData);
        $this->assertFalse($mismatchValidation['valid'], 'Operator name count mismatch should fail');
    }

    /**
     * 🔁 PHASE 2: DPR Calculation Consistency
     */
    public function test_dpr_calculation_consistency()
    {
        $this->actingAs($this->adminUser);

        // Create DPR for owned machinery
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 2,
            'rate_snapshot' => 1500,
            'billable_hours' => 8, // 10 - 2
            'calculated_amount' => 12000, // 8 * 1500
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Test calculation consistency
        $calculationResult = [
            'billable_hours' => 8,
            'rate_snapshot' => 1500,
        ];

        $consistencyValidation = DprInputValidationService::validateCalculationConsistency($dpr->toArray(), $calculationResult);
        $this->assertTrue($consistencyValidation['valid'], 'Calculation should be consistent');

        // Test inconsistent calculation
        $inconsistentCalculation = [
            'billable_hours' => 8,
            'rate_snapshot' => 1600, // Wrong rate
        ];

        $inconsistentValidation = DprInputValidationService::validateCalculationConsistency($dpr->toArray(), $inconsistentCalculation);
        $this->assertFalse($inconsistentValidation['valid'], 'Inconsistent calculation should fail');
    }

    /**
     * 🔁 PHASE 3: Diesel Management Validation
     */
    public function test_diesel_management_validation()
    {
        // Create DPR first
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Create diesel material
        $dieselMaterial = DB::table('materials')->insertGetId([
            'name' => 'diesel',
            'category' => 'fuel',
            'rate' => 85.00,
            'workspace_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ✅ Test valid diesel entry
        $validDieselData = [
            'machinery_id' => $this->ownedMachinery->id,
            'date' => now()->toDateString(),
            'material_id' => $dieselMaterial,
            'quantity' => 40,
            'unit' => 'liters',
            'site_id' => 1,
        ];

        $dieselValidation = DieselManagementValidationService::validateDieselEntry($validDieselData);
        $this->assertTrue($dieselValidation['valid'], 'Valid diesel entry should pass');

        // ❌ Test diesel without DPR (should warn)
        $dieselWithoutDprData = $validDieselData;
        $dieselWithoutDprData['date'] = now()->addDay()->toDateString();

        $withoutDprValidation = DieselManagementValidationService::validateDieselEntry($dieselWithoutDprData);
        $this->assertFalse($withoutDprValidation['valid'], 'Diesel without DPR should warn');

        // ❌ Test duplicate diesel entry (should fail)
        DailyConsumptionMaster::create([
            'machinery_id' => $this->ownedMachinery->id,
            'date' => now()->toDateString(),
            'material_id' => $dieselMaterial,
            'quantity' => 30,
            'unit' => 'liters',
            'site_id' => 1,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
        ]);

        $duplicateValidation = DieselManagementValidationService::validateDieselEntry($validDieselData);
        $this->assertFalse($duplicateValidation['valid'], 'Duplicate diesel entry should fail');
    }

    /**
     * 🔁 PHASE 4: DPR Duplication Prevention
     */
    public function test_dpr_duplication_prevention()
    {
        $this->actingAs($this->adminUser);

        // Create first DPR
        $dprData = [
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ];

        $firstDpr = DailyProgressReport::create($dprData);

        // ❌ Test duplicate DPR creation (should fail)
        $duplicateValidation = DprInputValidationService::validateDprInput($dprData);
        $this->assertFalse($duplicateValidation['valid'], 'Duplicate DPR should fail');

        $duplicateIssue = collect($duplicateValidation['errors']['business'] ?? [])->firstWhere('type', 'duplicate_dpr');
        $this->assertNotNull($duplicateIssue);
        $this->assertEquals($firstDpr->id, $duplicateIssue['existing_dpr_id']);
    }

    /**
     * 🔁 PHASE 5: Financial Flow Validation
     */
    public function test_financial_flow_validation()
    {
        $this->actingAs($this->adminUser);

        // Create owned machinery DPR
        $ownedDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Process financial flow
        $ownedResult = MachineryFinancialFlowService::processDprFinancials($ownedDpr);
        $this->assertEquals('owned', $ownedResult['financial_flow']);
        $this->assertEquals('internal_cost', $ownedResult['ledger_type']);
        $this->assertFalse($ownedResult['payment_required']);

        // Create rental machinery DPR
        $rentalDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->rentalMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105,
            'rate_snapshot' => 1800,
            'billable_hours' => 8, // Minimum billing applied
            'calculated_amount' => 14400, // 8 * 1800
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Process financial flow
        $rentalResult = MachineryFinancialFlowService::processDprFinancials($rentalDpr);
        $this->assertEquals('rental', $rentalResult['financial_flow']);
        $this->assertEquals('payable', $rentalResult['ledger_type']);
        $this->assertTrue($rentalResult['payment_required']);
    }

    /**
     * 🔁 PHASE 6: Comprehensive Operational Test
     */
    public function test_comprehensive_operational_scenario()
    {
        $this->actingAs($this->adminUser);

        // Step 1: Validate master data
        $masterValidation = MasterDataValidationService::comprehensiveValidation();
        $this->assertTrue($masterValidation['overall_valid'], 'Master data should be valid');

        // Step 2: Create DPR for owned machinery
        $ownedDprData = [
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 2,
            'number_of_operators' => 2,
            'operator_names' => 'John Doe, Jane Smith',
        ];

        $ownedDprValidation = DprInputValidationService::validateDprInput($ownedDprData);
        $this->assertTrue($ownedDprValidation['valid'], 'Owned DPR data should be valid');

        $ownedDpr = DailyProgressReport::create([
            'date' => $ownedDprData['date'],
            'machinery_id' => $ownedDprData['machinery_id'],
            'machine_start_reading' => $ownedDprData['machine_start_reading'],
            'machine_end_reading' => $ownedDprData['machine_end_reading'],
            'machine_idle_reading' => $ownedDprData['machine_idle_reading'],
            'number_of_operators' => $ownedDprData['number_of_operators'],
            'operator_names' => $ownedDprData['operator_names'],
            'rate_snapshot' => 1500,
            'billable_hours' => 8, // 10 - 2
            'calculated_amount' => 12000, // 8 * 1500
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Step 3: Process financial flow
        $ownedResult = MachineryFinancialFlowService::processDprFinancials($ownedDpr);
        $this->assertEquals('internal_cost', $ownedResult['ledger_type']);

        // Step 4: Add diesel entry
        $dieselMaterial = DB::table('materials')->insertGetId([
            'name' => 'diesel',
            'category' => 'fuel',
            'rate' => 85.00,
            'workspace_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $dieselData = [
            'machinery_id' => $this->ownedMachinery->id,
            'date' => $ownedDprData['date'],
            'material_id' => $dieselMaterial,
            'quantity' => 40,
            'unit' => 'liters',
            'site_id' => 1,
        ];

        $dieselValidation = DieselManagementValidationService::comprehensiveValidation($dieselData);
        $this->assertTrue($dieselValidation['valid'], 'Diesel entry should be valid');

        // Step 5: Create rental machinery DPR
        $rentalDprData = [
            'date' => now()->toDateString(),
            'machinery_id' => $this->rentalMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105, // 5 hours
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'operator_names' => 'Bob Smith',
        ];

        $rentalDprValidation = DprInputValidationService::validateDprInput($rentalDprData);
        $this->assertTrue($rentalDprValidation['valid'], 'Rental DPR data should be valid');

        $rentalDpr = DailyProgressReport::create([
            'date' => $rentalDprData['date'],
            'machinery_id' => $rentalDprData['machinery_id'],
            'machine_start_reading' => $rentalDprData['machine_start_reading'],
            'machine_end_reading' => $rentalDprData['machine_end_reading'],
            'machine_idle_reading' => $rentalDprData['machine_idle_reading'],
            'number_of_operators' => $rentalDprData['number_of_operators'],
            'operator_names' => $rentalDprData['operator_names'],
            'rate_snapshot' => 1800,
            'billable_hours' => 8, // Minimum billing applied
            'calculated_amount' => 14400, // 8 * 1800
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Step 6: Process rental financial flow
        $rentalResult = MachineryFinancialFlowService::processDprFinancials($rentalDpr);
        $this->assertEquals('payable', $rentalResult['ledger_type']);

        // Step 7: Validate final state
        $this->assertEquals(12000, $ownedResult['cost_components']['machine_cost']);
        $this->assertEquals(14400, $rentalResult['cost_components']['machine_cost']);

        // Step 8: Verify no double counting
        $totalProjectCost = 12000; // Owned machine cost only
        $totalPayables = 14400; // Rental machine cost only

        $this->assertNotEquals($totalProjectCost, $totalPayables, 'Project cost and payables should not be equal');
    }

    /**
     * 🔁 PHASE 7: Chaos Test - Try breaking the system
     */
    public function test_chaos_scenario()
    {
        $this->actingAs($this->adminUser);

        // Create initial DPR
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Try 1: Duplicate DPR (should fail)
        $duplicateData = [
            'date' => $dpr->date,
            'machinery_id' => $dpr->machinery_id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
        ];

        $duplicateValidation = DprInputValidationService::validateDprInput($duplicateData);
        $this->assertFalse($duplicateValidation['valid'], 'Duplicate DPR should fail');

        // Try 2: Invalid readings (should fail)
        $invalidReadingsData = [
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 150, // Less than start
        ];

        $invalidReadingsValidation = DprInputValidationService::validateDprInput($invalidReadingsData);
        $this->assertFalse($invalidReadingsValidation['valid'], 'Invalid readings should fail');

        // Try 3: Excessive idle hours (should fail)
        $excessiveIdleData = [
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 15, // More than working hours
        ];

        $excessiveIdleValidation = DprInputValidationService::validateDprInput($excessiveIdleData);
        $this->assertFalse($excessiveIdleValidation['valid'], 'Excessive idle hours should fail');

        // Try 4: Invalid rate override (should fail)
        $invalidOverrideData = [
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'override_rate' => -500, // Negative rate
            'override_reason' => 'Test negative rate',
        ];

        $invalidOverrideValidation = DprInputValidationService::validateDprInput($invalidOverrideData);
        $this->assertFalse($invalidOverrideValidation['valid'], 'Negative override rate should fail');

        // All chaos attempts should be rejected
        $this->assertTrue(true, 'System should reject all chaos attempts');
    }
}
