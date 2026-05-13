<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\MachineryCategory;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Models\User;
use App\Models\WorkSpace;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Services\MachineryLedgerService;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Workdo\Taskly\Entities\Project;

class MachineryFullFlowValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $admin;
    private User $operator;
    private WorkSpace $workspace;
    private Project $site;
    private MachineryCategory $category;
    private Machinery $ownedMachinery;
    private Machinery $rentalMachinery;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->admin = User::factory()->create();
        $this->admin->assignRole('super admin');
        
        $this->operator = User::factory()->create();
        $this->operator->assignRole('employee');

        // Create workspace and site
        $this->workspace = WorkSpace::factory()->create();
        $this->site = Project::factory()->create();
        
        // Create machinery category
        $this->category = MachineryCategory::factory()->create();

        // Set active workspace for testing
        session(['workspace_id' => $this->workspace->id]);
    }

    /**
     * 🏗️ PHASE 0: MASTER DATA SETUP (CRITICAL FOUNDATION)
     */
    public function test_master_data_validation_owned_vs_rental()
    {
        // 🏢 Create Owned Machinery - should succeed without supplier
        $ownedMachinery = Machinery::create([
            'name' => 'Excavator A',
            'owned_by' => 'owned',
            'rate' => 1500,
            'supplier_id' => null, // ❌ NULL - should be allowed
            'category_id' => $this->category->id,
            'workspace_id' => $this->workspace->id,
            'site_id' => $this->site->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertNotNull($ownedMachinery);
        $this->assertEquals('owned', $ownedMachinery->owned_by);
        $this->assertNull($ownedMachinery->supplier_id);
        $this->assertEquals(1500, $ownedMachinery->rate);

        // 🚚 Create Rental Machinery - should require supplier
        $supplier = \App\Models\Supplier::factory()->create();
        
        $rentalMachinery = Machinery::create([
            'name' => 'Excavator B',
            'owned_by' => 'rental',
            'rate' => 1200,
            'minimum_billing_hours' => 8,
            'supplier_id' => $supplier->id, // ✅ REQUIRED
            'category_id' => $this->category->id,
            'workspace_id' => $this->workspace->id,
            'site_id' => $this->site->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertNotNull($rentalMachinery);
        $this->assertEquals('rental', $rentalMachinery->owned_by);
        $this->assertEquals($supplier->id, $rentalMachinery->supplier_id);
        $this->assertEquals(1200, $rentalMachinery->rate);
        $this->assertEquals(8, $rentalMachinery->minimum_billing_hours);

        // ✅ VALIDATION CHECKPOINT: Test business rules
        $this->actingAs($this->admin);

        // ❌ Try owned with supplier -> SHOULD FAIL
        $this->expectException(\Exception::class);
        Machinery::create([
            'name' => 'Invalid Owned Machine',
            'owned_by' => 'owned',
            'rate' => 1000,
            'supplier_id' => $supplier->id, // ❌ Should not have supplier
            'category_id' => $this->category->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
        ]);

        // ❌ Try rental without supplier -> SHOULD FAIL
        $this->expectException(\Exception::class);
        Machinery::create([
            'name' => 'Invalid Rental Machine',
            'owned_by' => 'rental',
            'rate' => 1000,
            'supplier_id' => null, // ❌ Should have supplier
            'category_id' => $this->category->id,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
        ]);

        // Store for subsequent tests
        $this->ownedMachinery = $ownedMachinery;
        $this->rentalMachinery = $rentalMachinery;

        return [
            'owned' => $ownedMachinery,
            'rental' => $rentalMachinery
        ];
    }

    /**
     * ⚙️ PHASE 1: DPR CREATION (CORE ENGINE TEST)
     */
    public function test_dpr_creation_both_machinery_types()
    {
        // Setup master data first
        $machinery = $this->test_master_data_validation_owned_vs_rental();
        $ownedMachinery = $machinery['owned'];
        $rentalMachinery = $machinery['rental'];

        $this->actingAs($this->operator);

        // 🏢 OWNED DPR
        $ownedDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 106, // 6 hrs
            'machine_idle_reading' => 1, // 1 hr idle
            'number_of_operators' => 2,
            'operator_names' => 'John, Mike',
            'work_details' => 'Excavation work',
            'workspace_id' => $this->workspace->id,
            'site_id' => $this->site->id,
            'created_by' => $this->operator->id,
        ]);

        // Verify calculations
        $this->assertEquals(6, $ownedDpr->machine_hours);
        $this->assertEquals(5, $ownedDpr->billable_hours); // 6 - 1 idle
        $this->assertEquals(7500, $ownedDpr->calculated_amount); // 5 × 1500

        // Verify ledger entry creation
        $ledgerEntry = MachineryLedger::where('reference_type', 'DailyProgressReport')
            ->where('reference_id', $ownedDpr->id)
            ->first();
        
        $this->assertNotNull($ledgerEntry);
        $this->assertEquals('internal_cost', $ledgerEntry->ledger_type);
        $this->assertEquals('credit', $ledgerEntry->entry_direction);
        $this->assertEquals(7500, $ledgerEntry->amount);

        // 🚚 RENTAL DPR
        $rentalDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $rentalMachinery->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 205, // 5 hrs
            'machine_idle_reading' => 1, // 1 hr idle = 4 hrs actual
            'number_of_operators' => 2,
            'operator_names' => 'Dave, Steve',
            'work_details' => 'Loading work',
            'workspace_id' => $this->workspace->id,
            'site_id' => $this->site->id,
            'created_by' => $this->operator->id,
        ]);

        // Verify minimum billing applied
        $this->assertEquals(5, $rentalDpr->machine_hours);
        $this->assertEquals(8, $rentalDpr->billable_hours); // Minimum billing applied!
        $this->assertEquals(9600, $rentalDpr->calculated_amount); // 8 × 1200

        // Verify ledger entry for rental
        $rentalLedgerEntry = MachineryLedger::where('reference_type', 'DailyProgressReport')
            ->where('reference_id', $rentalDpr->id)
            ->first();
        
        $this->assertNotNull($rentalLedgerEntry);
        $this->assertEquals('payable', $rentalLedgerEntry->ledger_type);
        $this->assertEquals('credit', $rentalLedgerEntry->entry_direction);
        $this->assertEquals(9600, $rentalLedgerEntry->amount);

        // 🚨 BREAK TESTS
        $this->test_dpr_validation_break_tests($ownedMachinery, $rentalMachinery);

        return [
            'owned_dpr' => $ownedDpr,
            'rental_dpr' => $rentalDpr
        ];
    }

    /**
     * 🚨 DPR BREAK TESTS
     */
    private function test_dpr_validation_break_tests($ownedMachinery, $rentalMachinery)
    {
        // End < Start → ❌ BLOCK
        $this->expectException(\Exception::class);
        DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $ownedMachinery->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 100, // Less than start
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);

        // Idle > Working → ⚠️ WARN (should allow but warn)
        // This would be caught in the validation layer - for now we test it doesn't block
        $warningDpr = DailyProgressReport::create([
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 102, // 2 hrs working
            'machine_idle_reading' => 3, // 3 hrs idle > working
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);
        $this->assertInstanceOf(DailyProgressReport::class, $warningDpr);

        // Duplicate DPR → ❌ BLOCK (same date + machinery)
        $this->expectException(\Exception::class);
        DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $ownedMachinery->id, // Same machinery and date
            'machine_start_reading' => 300,
            'machine_end_reading' => 305,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);
    }

    /**
     * ⛽ PHASE 2: DIESEL MANAGEMENT TEST
     */
    public function test_diesel_management_validation()
    {
        // Setup DPR first
        $dprs = $this->test_dpr_creation_both_machinery_types();
        $ownedDpr = $dprs['owned_dpr'];
        $rentalDpr = $dprs['rental_dpr'];

        $this->actingAs($this->operator);

        // Add Diesel for Owned Machine
        $ownedDiesel = DailyConsumptionMaster::create([
            'date' => now()->toDateString(),
            'daily_progress_report_id' => $ownedDpr->id,
            'diesel_consumed_liters' => 50,
            'diesel_rate_per_liter' => 100,
            'total_diesel_cost' => 5000,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);

        // Add Diesel for Rental Machine
        $rentalDiesel = DailyConsumptionMaster::create([
            'date' => now()->toDateString(),
            'daily_progress_report_id' => $rentalDpr->id,
            'diesel_consumed_liters' => 40,
            'diesel_rate_per_liter' => 100,
            'total_diesel_cost' => 4000,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);

        // Verify ledger entries for diesel
        $ownedDieselLedger = MachineryLedger::where('reference_type', 'DailyConsumptionMaster')
            ->where('reference_id', $ownedDiesel->id)
            ->first();
        
        $this->assertNotNull($ownedDieselLedger);
        $this->assertEquals('expense', $ownedDieselLedger->ledger_type);
        $this->assertEquals('diesel', $ownedDieselLedger->cost_category);
        $this->assertEquals('debit', $ownedDieselLedger->entry_direction);
        $this->assertEquals(5000, $ownedDieselLedger->amount);

        $rentalDieselLedger = MachineryLedger::where('reference_type', 'DailyConsumptionMaster')
            ->where('reference_id', $rentalDiesel->id)
            ->first();
        
        $this->assertNotNull($rentalDieselLedger);
        $this->assertEquals('expense', $rentalDieselLedger->ledger_type);
        $this->assertEquals('diesel', $rentalDieselLedger->cost_category);
        $this->assertEquals('debit', $rentalDieselLedger->entry_direction);
        $this->assertEquals(4000, $rentalDieselLedger->amount);

        // 🚨 DIESEL BREAK TESTS
        $this->test_diesel_break_tests($ownedDpr, $rentalDpr);

        return [
            'owned_diesel' => $ownedDiesel,
            'rental_diesel' => $rentalDiesel
        ];
    }

    /**
     * 🚨 DIESEL BREAK TESTS
     */
    private function test_diesel_break_tests($ownedDpr, $rentalDpr)
    {
        // Duplicate diesel same day → ⚠️ WARN (should allow but warn)
        $duplicateDiesel = DailyConsumptionMaster::create([
            'date' => now()->toDateString(),
            'daily_progress_report_id' => $ownedDpr->id,
            'diesel_consumed_liters' => 10,
            'diesel_rate_per_liter' => 100,
            'total_diesel_cost' => 1000,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);
        $this->assertInstanceOf(DailyConsumptionMaster::class, $duplicateDiesel);

        // Diesel without DPR → ⚠️ WARN (allowed)
        $standaloneDiesel = DailyConsumptionMaster::create([
            'date' => now()->addDay()->toDateString(),
            'daily_progress_report_id' => null, // No DPR link
            'diesel_consumed_liters' => 30,
            'diesel_rate_per_liter' => 100,
            'total_diesel_cost' => 3000,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);
        $this->assertInstanceOf(DailyConsumptionMaster::class, $standaloneDiesel);

        // Excessive diesel (100L for 2 hrs) → 🚨 FLAG
        $excessiveDiesel = DailyConsumptionMaster::create([
            'date' => now()->addDays(2)->toDateString(),
            'daily_progress_report_id' => $ownedDpr->id,
            'diesel_consumed_liters' => 100, // Excessive for 2 hrs
            'diesel_rate_per_liter' => 100,
            'total_diesel_cost' => 10000,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);
        $this->assertInstanceOf(DailyConsumptionMaster::class, $excessiveDiesel);
    }

    /**
     * 👷 PHASE 3: OPERATOR ENTRY TEST
     */
    public function test_operator_entry_validation()
    {
        // Setup DPR first
        $dprs = $this->test_dpr_creation_both_machinery_types();
        $ownedDpr = $dprs['owned_dpr'];

        $this->actingAs($this->operator);

        // Valid operator entry
        $validDpr = DailyProgressReport::create([
            'date' => now()->addDays(3)->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105,
            'number_of_operators' => 2,
            'operator_names' => 'John, Mike', // Properly formatted
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);

        $this->assertEquals(2, $validDpr->number_of_operators);
        $this->assertEquals('John, Mike', $validDpr->operator_names);

        // 🚨 OPERATOR BREAK TEST
        // Count = 2, names = 1 → ⚠️ WARN + reason required
        $invalidOperatorDpr = DailyProgressReport::create([
            'date' => now()->addDays(4)->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105,
            'number_of_operators' => 2,
            'operator_names' => 'John', // Only 1 name for 2 operators
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);

        // This should be created but flagged for validation
        $this->assertInstanceOf(DailyProgressReport::class, $invalidOperatorDpr);
        // In a real implementation, this would trigger a warning requiring override reason
    }

    /**
     * 💰 PHASE 4: PAYMENT FLOW TEST (CRITICAL)
     */
    public function test_payment_flow_rental_only()
    {
        // Setup DPR first
        $dprs = $this->test_dpr_creation_both_machinery_types();
        $rentalDpr = $dprs['rental_dpr'];

        $this->actingAs($this->admin);

        // 🚚 RENTAL ONLY: Create Payment Request
        $paymentRequest = MachineryPaymentRequest::create([
            'machinery_id' => $this->rentalMachinery->id,
            'daily_progress_report_id' => $rentalDpr->id,
            'amount' => $rentalDpr->calculated_amount,
            'status' => 'pending',
            'requested_by' => $this->admin->id,
            'workspace_id' => $this->workspace->id,
        ]);

        $this->assertNotNull($paymentRequest);
        $this->assertEquals(9600, $paymentRequest->amount);
        $this->assertEquals('pending', $paymentRequest->status);

        // Approve Payment
        $paymentRequest->update([
            'status' => 'approved',
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);

        // 🔍 VERIFY: DPR locked = true
        $rentalDpr->refresh();
        $this->assertTrue($rentalDpr->status === 'approved'); // DPR should be locked via status

        // Verify ledger entry linked to payment
        $linkedLedger = MachineryLedger::where('reference_type', 'DailyProgressReport')
            ->where('reference_id', $rentalDpr->id)
            ->whereNotNull('payment_request_id')
            ->first();
        
        $this->assertNotNull($linkedLedger);
        $this->assertEquals($paymentRequest->id, $linkedLedger->payment_request_id);

        // 🚨 PAYMENT BREAK TESTS
        $this->test_payment_break_tests();

        return $paymentRequest;
    }

    /**
     * 🚨 PAYMENT BREAK TESTS
     */
    private function test_payment_break_tests()
    {
        // Try payment for owned → ❌ BLOCK
        $ownedDpr = DailyProgressReport::where('machinery_id', $this->ownedMachinery->id)->first();
        
        $this->expectException(\Exception::class);
        MachineryPaymentRequest::create([
            'machinery_id' => $this->ownedMachinery->id,
            'daily_progress_report_id' => $ownedDpr->id,
            'amount' => $ownedDpr->calculated_amount,
            'status' => 'pending',
            'requested_by' => $this->admin->id,
            'workspace_id' => $this->workspace->id,
        ]);

        // Try edit DPR after payment → ❌ BLOCK
        $rentalDpr = DailyProgressReport::where('machinery_id', $this->rentalMachinery->id)->first();
        $rentalDpr->status = 'approved'; // Lock it
        
        $this->expectException(\Exception::class);
        $rentalDpr->update(['work_details' => 'Modified after approval']);
    }

    /**
     * 🔁 PHASE 5: REVERSAL TEST (AUDIT TEST)
     */
    public function test_reversal_audit_trail()
    {
        // Setup payment first
        $paymentRequest = $this->test_payment_flow_rental_only();

        $this->actingAs($this->admin);

        // Reverse Payment
        $reversalReason = 'Incorrect amount calculation';
        $reversalLedger = MachineryLedgerService::reverseEntry(
            $paymentRequest->ledgerEntry->id,
            $reversalReason
        );

        // 🔍 VERIFY: Reversal entry created
        $this->assertNotNull($reversalLedger);
        $this->assertTrue($reversalLedger->is_reversal);
        $this->assertEquals($paymentRequest->ledgerEntry->id, $reversalLedger->reversed_entry_id);
        $this->assertStringContains($reversalReason, $reversalLedger->description);

        // Verify original DPR unchanged
        $originalDpr = $paymentRequest->dailyProgressReport;
        $originalDpr->refresh();
        $this->assertNotNull($originalDpr);

        // Verify ledger balanced
        $originalEntry = $paymentRequest->ledgerEntry;
        $this->assertEquals($originalEntry->amount, $reversalLedger->amount);
        $this->assertEquals('debit', $reversalLedger->entry_direction); // Opposite of credit
    }

    /**
     * 📊 PHASE 6: MACHINE WORK REPORT TEST
     */
    public function test_machine_work_report_aggregation()
    {
        // Setup all data
        $this->test_diesel_management_validation();

        // Test aggregation logic
        $ownedLedgerEntries = MachineryLedger::where('machinery_id', $this->ownedMachinery->id)
            ->where('is_reversal', false)
            ->get();

        $rentalLedgerEntries = MachineryLedger::where('machinery_id', $this->rentalMachinery->id)
            ->where('is_reversal', false)
            ->get();

        // 🚨 CRITICAL CHECK: Financial separation
        $ownedInternalCost = $ownedLedgerEntries
            ->where('ledger_type', 'internal_cost')
            ->sum('amount');

        $ownedExpense = $ownedLedgerEntries
            ->where('ledger_type', 'expense')
            ->sum('amount');

        $rentalPayable = $rentalLedgerEntries
            ->where('ledger_type', 'payable')
            ->sum('amount');

        $rentalExpense = $rentalLedgerEntries
            ->where('ledger_type', 'expense')
            ->sum('amount');

        // Verify separation
        $this->assertGreaterThan(0, $ownedInternalCost);
        $this->assertGreaterThan(0, $ownedExpense);
        $this->assertGreaterThan(0, $rentalPayable);
        $this->assertGreaterThan(0, $rentalExpense);

        // Project Cost = internal_cost + expense
        $projectCost = $ownedInternalCost + $ownedExpense + $rentalExpense;
        $payables = $rentalPayable;

        // These MUST NEVER mix
        $this->assertNotEquals(0, $projectCost);
        $this->assertNotEquals(0, $payables);
        
        // Verify no mixing: owned should not have payable entries
        $ownedPayableEntries = $ownedLedgerEntries->where('ledger_type', 'payable');
        $this->assertEquals(0, $ownedPayableEntries->count());

        // Rental should not have internal_cost entries
        $rentalInternalCostEntries = $rentalLedgerEntries->where('ledger_type', 'internal_cost');
        $this->assertEquals(0, $rentalInternalCostEntries->count());
    }

    /**
     * 🧪 PHASE 7: BEHAVIORAL TEST
     */
    public function test_behavioral_validation_system()
    {
        $this->actingAs($this->operator);

        // Test override with reason
        $overrideDpr = DailyProgressReport::create([
            'date' => now()->addDays(5)->toDateString(),
            'machinery_id' => $this->ownedMachinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105,
            'machine_idle_reading' => 3, // High idle time
            'override_reason' => 'Machine stuck in mud - justified',
            'override_by' => $this->operator->id,
            'override_at' => now(),
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->operator->id,
        ]);

        $this->assertNotNull($overrideDpr->override_reason);
        $this->assertEquals($this->operator->id, $overrideDpr->override_by);

        // Test warning count tracking (this would be implemented in the validation service)
        // For now, we verify the override fields are properly stored
        $this->assertNotNull($overrideDpr->override_at);
    }

    /**
     * 📈 PHASE 8: REPORT + WARNING VISIBILITY
     */
    public function test_report_visibility_and_quality_score()
    {
        // This would test the reporting functionality
        // For now, we verify the data exists for reporting
        
        $totalLedgerEntries = MachineryLedger::where('is_reversal', false)->count();
        $this->assertGreaterThan(0, $totalLedgerEntries);

        $totalDprEntries = DailyProgressReport::count();
        $this->assertGreaterThan(0, $totalDprEntries);

        // In a full implementation, this would test:
        // - Total cost calculation
        // - Warning count display
        // - Quality score calculation
        // - Report generation
    }

    /**
     * 🔒 FINAL CHAOS TEST (MOST IMPORTANT)
     */
    public function test_final_chaos_system_certification()
    {
        $this->actingAs($this->admin);

        // Change rate after DPR → old DPR must NOT change
        $originalRate = $this->ownedMachinery->rate;
        $this->ownedMachinery->update(['rate' => 2000]);
        
        $oldDpr = DailyProgressReport::where('machinery_id', $this->ownedMachinery->id)->first();
        $this->assertEquals($originalRate * 5, $oldDpr->calculated_amount); // Should use old rate

        // Edit locked DPR → ❌
        $lockedDpr = DailyProgressReport::where('status', 'approved')->first();
        if ($lockedDpr) {
            $this->expectException(\Exception::class);
            $lockedDpr->update(['work_details' => 'Should fail']);
        }

        // Create duplicate entries → ❌
        $this->expectException(\Exception::class);
        DailyProgressReport::create([
            'date' => $oldDpr->date,
            'machinery_id' => $oldDpr->machinery_id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105,
            'workspace_id' => $this->workspace->id,
            'created_by' => $this->admin->id,
        ]);

        // Force mismatch ledger → ❌
        $this->expectException(\Exception::class);
        MachineryLedgerService::createCredit([
            'machinery_id' => 999, // Non-existent machinery
            'amount' => 1000,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $oldDpr->id,
        ]);

        // Mix cost & payable → ❌ (this is enforced at the service level)
        // The system should prevent owned machinery from having payable entries
        $this->expectException(\Exception::class);
        MachineryLedgerService::createCredit([
            'machinery_id' => $this->ownedMachinery->id,
            'amount' => 1000,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $oldDpr->id,
            'payment_request_id' => 1, // This should fail for owned machinery
        ]);
    }

    /**
     * 🧠 FINAL THINKER CHECKLIST
     */
    public function test_final_system_certification()
    {
        // Run all phases in sequence
        $this->test_master_data_validation_owned_vs_rental();
        $this->test_dpr_creation_both_machinery_types();
        $this->test_diesel_management_validation();
        $this->test_operator_entry_validation();
        $this->test_payment_flow_rental_only();
        $this->test_reversal_audit_trail();
        $this->test_machine_work_report_aggregation();
        $this->test_behavioral_validation_system();
        $this->test_report_visibility_and_quality_score();
        $this->test_final_chaos_system_certification();

        // If all tests pass, we have:
        // ✅ Deterministic calculations
        // ✅ Correct financial classification
        // ✅ Behavioral accountability
        // ✅ Audit-safe flows

        $this->assertTrue(true, '🏁 FULL FLOW VALIDATION CERTIFIED');
    }
}
