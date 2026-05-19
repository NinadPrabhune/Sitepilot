<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\Supplier;
use App\Models\User;
use App\Models\DailyProgressReport;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyConsumptionDetails;
use App\Domain\Machinery\Models\MachineryLedger;
use App\Domain\Machinery\Models\MachineryPaymentRequest;
use App\Domain\Machinery\Models\MachineryPaymentPeriod;
use App\Domain\Machinery\Services\MachineryLedgerService;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MachineryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;
    protected Machinery $ownedMachine;
    protected Machinery $rentalMachine;
    protected Machinery $complexRentalMachine;
    protected Supplier $testSupplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create(['email' => 'test@example.com']);
        $this->actingAs($this->testUser);

        $this->testSupplier = Supplier::factory()->create();

        // Create test machinery
        $this->ownedMachine = Machinery::factory()->create([
            'name' => 'OWN-001 Test Owned',
            'owned_by' => 'owned',
            'rate' => 1000,
            'vehicle_number' => 'OWN001',
            'operational_status' => 'active',
        ]);

        $this->rentalMachine = Machinery::factory()->create([
            'name' => 'RENT-001 Test Rental',
            'owned_by' => 'rental',
            'rate' => 1500,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 0,
            'diesel_by_company' => false,
            'operator_by_supplier' => false,
            'supplier_id' => $this->testSupplier->id,
            'vehicle_number' => 'RENT001',
            'operational_status' => 'active',
        ]);

        $this->complexRentalMachine = Machinery::factory()->create([
            'name' => 'RENT-002 Test Complex Rental',
            'owned_by' => 'rental',
            'rate' => 1200,
            'rate_type' => 'hourly',
            'minimum_billing_hours' => 8,
            'diesel_by_company' => true,
            'operator_by_supplier' => true,
            'number_of_operators' => 2,
            'supplier_id' => $this->testSupplier->id,
            'vehicle_number' => 'RENT002',
            'operational_status' => 'active',
        ]);
    }

    /** @test */
    public function it_can_create_daily_progress_report()
    {
        $dprData = [
            'date' => now()->format('Y-m-d'),
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 108,
            'number_of_operators' => 1,
            'work_details' => 'Test operation',
            'consumption_type' => 'fuel',
            'items' => [
                [
                    'material_id' => 1, // Assuming material 1 exists
                    'quantity' => 40,
                    'unit' => 'liters',
                    'remarks' => 'Diesel consumption'
                ]
            ],
            'site_id' => 1,
        ];

        $response = $this->post('/daily-progress-reports', $dprData);

        $response->assertStatus(302); // Redirect after successful creation

        $this->assertDatabaseHas('daily_progress_reports', [
            'machinery_id' => $this->ownedMachine->id,
            'billable_hours' => 8,
            'calculated_amount' => 8000, // 8 hours * 1000 rate
        ]);

        $dpr = DailyProgressReport::first();
        $this->assertEquals(8, $dpr->billable_hours);
        $this->assertEquals(8000, $dpr->calculated_amount);
    }

    /** @test */
    public function it_prevents_duplicate_dpr_for_same_machine_and_date()
    {
        // Create first DPR
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'approved'
        ]);

        // Try to create duplicate
        $dprData = [
            'date' => now()->format('Y-m-d'),
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 208,
            'consumption_type' => 'fuel',
            'items' => [],
            'site_id' => 1,
        ];

        $response = $this->post('/daily-progress-reports', $dprData);

        $response->assertStatus(422);
        $this->assertEquals(1, DailyProgressReport::where('machinery_id', $this->ownedMachine->id)
            ->where('date', now()->format('Y-m-d'))->count());
    }

    /** @test */
    public function it_validates_machine_readings()
    {
        $dprData = [
            'date' => now()->format('Y-m-d'),
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 150,
            'machine_end_reading' => 140, // End < Start - should fail
            'consumption_type' => 'fuel',
            'items' => [],
            'site_id' => 1,
        ];

        $response = $this->post('/daily-progress-reports', $dprData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['machine_end_reading']);
    }

    /** @test */
    public function it_creates_ledger_entry_on_dpr_approval()
    {
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 10,
            'calculated_amount' => 10000,
            'status' => 'pending'
        ]);

        // Approve DPR
        $response = $this->put("/daily-progress-reports/{$dpr->id}/approve");

        $response->assertStatus(200);

        $dpr->refresh();
        $this->assertEquals('approved', $dpr->status);

        // Check ledger entry was created
        $this->assertDatabaseHas('machinery_ledger', [
            'machinery_id' => $this->ownedMachine->id,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'entry_direction' => 'credit',
            'amount' => 10000,
        ]);
    }

    /** @test */
    public function it_enforces_minimum_billing_hours_for_rental_machinery()
    {
        // Create DPR with less than minimum billing hours
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->complexRentalMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 6, // Less than 8 hour minimum
            'calculated_amount' => 7200, // 6 * 1200
            'status' => 'pending'
        ]);

        // Approve DPR
        $this->put("/daily-progress-reports/{$dpr->id}/approve");

        $dpr->refresh();

        // For complex rental, should still be calculated at minimum billing
        $expectedAmount = 8 * 1200; // Minimum billing * rate
        $this->assertEquals($expectedAmount, $dpr->calculated_amount);

        // Ledger should reflect minimum billing
        $ledgerEntry = MachineryLedger::where('reference_id', $dpr->id)
            ->where('reference_type', 'DailyProgressReport')
            ->first();

        $this->assertEquals($expectedAmount, $ledgerEntry->amount);
    }

    /** @test */
    public function it_creates_payment_request_from_ledger_entries()
    {
        // Create multiple DPRs and approve them
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'billable_hours' => 8,
            'calculated_amount' => 12000,
            'status' => 'approved'
        ]);

        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'date' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'status' => 'approved'
        ]);

        // Create corresponding ledger entries
        MachineryLedgerService::createCredit([
            'machinery_id' => $this->rentalMachine->id,
            'amount' => $dpr1->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr1->id,
            'date' => $dpr1->date,
        ]);

        MachineryLedgerService::createCredit([
            'machinery_id' => $this->rentalMachine->id,
            'amount' => $dpr2->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr2->id,
            'date' => $dpr2->date,
        ]);

        // Create payment request
        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));
        
        $paymentRequest = $service->createFromLedger(
            $this->rentalMachine->id,
            $this->testSupplier->id,
            Carbon::now()->subDays(2)->format('Y-m-d'),
            Carbon::now()->subDays(1)->format('Y-m-d'),
            $this->testUser->id
        );

        $this->assertInstanceOf(MachineryPaymentRequest::class, $paymentRequest);
        $this->assertEquals(27000, $paymentRequest->credits); // 12000 + 15000
        $this->assertEquals($this->rentalMachine->id, $paymentRequest->machinery_id);
        $this->assertEquals($this->testSupplier->id, $paymentRequest->supplier_id);
    }

    /** @test */
    public function it_prevents_overlapping_payment_periods()
    {
        // Create first payment request and lock its period
        $paymentRequest1 = MachineryPaymentRequest::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'period_start' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'period_end' => Carbon::now()->subDays(3)->format('Y-m-d'),
            'status' => 'approved'
        ]);

        // Lock the period
        MachineryPaymentPeriod::create([
            'machinery_id' => $this->rentalMachine->id,
            'start_date' => $paymentRequest1->period_start,
            'end_date' => $paymentRequest1->period_end,
            'is_locked' => true,
            'payment_request_id' => $paymentRequest1->id,
        ]);

        // Try to create overlapping payment request
        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Payment period overlaps with existing locked period');

        $service->createFromLedger(
            $this->rentalMachine->id,
            $this->testSupplier->id,
            Carbon::now()->subDays(4)->format('Y-m-d'), // Overlaps
            Carbon::now()->subDays(2)->format('Y-m-d'),
            $this->testUser->id
        );
    }

    /** @test */
    public function it_handles_payment_request_workflow_correctly()
    {
        // Create ledger entries
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 8,
            'calculated_amount' => 12000,
            'status' => 'approved'
        ]);

        MachineryLedgerService::createCredit([
            'machinery_id' => $this->rentalMachine->id,
            'amount' => $dpr->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'date' => $dpr->date,
        ]);

        // Create payment request
        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));
        $paymentRequest = $service->createFromLedger(
            $this->rentalMachine->id,
            $this->testSupplier->id,
            now()->format('Y-m-d'),
            now()->format('Y-m-d'),
            $this->testUser->id
        );

        // Submit
        $service->submit($paymentRequest->id, $this->testUser->id);
        $paymentRequest->refresh();
        $this->assertEquals('submitted', $paymentRequest->status);

        // Verify
        $service->verify($paymentRequest->id, $this->testUser->id);
        $paymentRequest->refresh();
        $this->assertEquals('verified', $paymentRequest->status);

        // Approve
        $service->approve($paymentRequest->id, $this->testUser->id);
        $paymentRequest->refresh();
        $this->assertEquals('approved', $paymentRequest->status);

        // Check period is locked
        $this->assertDatabaseHas('machinery_payment_periods', [
            'machinery_id' => $this->rentalMachine->id,
            'payment_request_id' => $paymentRequest->id,
            'is_locked' => true,
        ]);

        // Check ledger entries are linked
        $linkedCount = MachineryLedger::where('payment_request_id', $paymentRequest->id)->count();
        $this->assertEquals(1, $linkedCount);
    }

    /** @test */
    public function it_reverses_ledger_entries_on_payment_rejection()
    {
        // Create and approve payment request
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 8,
            'calculated_amount' => 12000,
            'status' => 'approved'
        ]);

        $ledgerEntry = MachineryLedgerService::createCredit([
            'machinery_id' => $this->rentalMachine->id,
            'amount' => $dpr->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'date' => $dpr->date,
        ]);

        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));
        $paymentRequest = $service->createFromLedger(
            $this->rentalMachine->id,
            $this->testSupplier->id,
            now()->format('Y-m-d'),
            now()->format('Y-m-d'),
            $this->testUser->id
        );

        $service->submit($paymentRequest->id, $this->testUser->id);
        $service->verify($paymentRequest->id, $this->testUser->id);
        $service->approve($paymentRequest->id, $this->testUser->id);

        // Reject payment request
        $service->reject($paymentRequest->id, $this->testUser->id, 'Test rejection');

        // Check reversal entry was created
        $reversalEntry = MachineryLedger::where('reversal_of_id', $ledgerEntry->id)->first();
        $this->assertNotNull($reversalEntry);
        $this->assertEquals('debit', $reversalEntry->entry_direction);
        $this->assertEquals($ledgerEntry->amount, $reversalEntry->amount);
        $this->assertTrue($reversalEntry->is_reversal);

        // Check original entry is unlinked
        $ledgerEntry->refresh();
        $this->assertNull($ledgerEntry->payment_request_id);
    }

    /** @test */
    public function it_maintains_ledger_integrity_through_complete_workflow()
    {
        // Create multiple DPRs across different machines
        $dprs = [];
        
        // Owned machine DPRs
        for ($i = 0; $i < 3; $i++) {
            $dpr = DailyProgressReport::factory()->create([
                'machinery_id' => $this->ownedMachine->id,
                'date' => Carbon::now()->subDays($i)->format('Y-m-d'),
                'billable_hours' => 8 + $i,
                'calculated_amount' => (8 + $i) * $this->ownedMachine->rate,
                'status' => 'approved'
            ]);
            $dprs[] = $dpr;
        }

        // Rental machine DPRs
        for ($i = 0; $i < 2; $i++) {
            $dpr = DailyProgressReport::factory()->create([
                'machinery_id' => $this->rentalMachine->id,
                'date' => Carbon::now()->subDays($i)->format('Y-m-d'),
                'billable_hours' => 8 + $i,
                'calculated_amount' => (8 + $i) * $this->rentalMachine->rate,
                'status' => 'approved'
            ]);
            $dprs[] = $dpr;
        }

        // Create ledger entries for all DPRs
        foreach ($dprs as $dpr) {
            MachineryLedgerService::createCredit([
                'machinery_id' => $dpr->machinery_id,
                'amount' => $dpr->calculated_amount,
                'reference_type' => 'DailyProgressReport',
                'reference_id' => $dpr->id,
                'date' => $dpr->date,
            ]);
        }

        // Create payment requests for each machine
        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));
        
        $ownedPaymentRequest = $service->createFromLedger(
            $this->ownedMachine->id,
            null, // No supplier for owned
            Carbon::now()->subDays(2)->format('Y-m-d'),
            Carbon::now()->format('Y-m-d'),
            $this->testUser->id
        );

        $rentalPaymentRequest = $service->createFromLedger(
            $this->rentalMachine->id,
            $this->testSupplier->id,
            Carbon::now()->subDays(1)->format('Y-m-d'),
            Carbon::now()->format('Y-m-d'),
            $this->testUser->id
        );

        // Approve both payment requests
        $service->submit($ownedPaymentRequest->id, $this->testUser->id);
        $service->verify($ownedPaymentRequest->id, $this->testUser->id);
        $service->approve($ownedPaymentRequest->id, $this->testUser->id);

        $service->submit($rentalPaymentRequest->id, $this->testUser->id);
        $service->verify($rentalPaymentRequest->id, $this->testUser->id);
        $service->approve($rentalPaymentRequest->id, $this->testUser->id);

        // Verify ledger integrity
        $ownedLedgerBalance = MachineryLedger::where('machinery_id', $this->ownedMachine->id)
            ->where('is_reversal', false)
            ->sum('running_balance');

        $rentalLedgerBalance = MachineryLedger::where('machinery_id', $this->rentalMachine->id)
            ->where('is_reversal', false)
            ->sum('running_balance');

        // All entries should be linked to payment requests
        $unlinkedEntries = MachineryLedger::whereNull('payment_request_id')
            ->where('is_reversal', false)
            ->where('reference_type', 'DailyProgressReport')
            ->count();

        $this->assertEquals(0, $unlinkedEntries);

        // Periods should be locked
        $this->assertEquals(2, MachineryPaymentPeriod::where('is_locked', true)->count());

        // Payment requests should be approved
        $this->assertEquals('approved', $ownedPaymentRequest->fresh()->status);
        $this->assertEquals('approved', $rentalPaymentRequest->fresh()->status);
    }

    /** @test */
    public function it_handles_diesel_consumption_correctly()
    {
        // Create DPR with diesel consumption
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->complexRentalMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 8,
            'diesel_consumption' => 40,
            'status' => 'approved'
        ]);

        // Create consumption master and details
        $consumptionMaster = DailyConsumptionMaster::factory()->create([
            'machinery_id' => $this->complexRentalMachine->id,
            'consumption_date' => now()->format('Y-m-d'),
            'daily_progress_report_id' => $dpr->id,
        ]);

        $consumptionDetail = DailyConsumptionDetails::factory()->create([
            'daily_consumption_master_id' => $consumptionMaster->id,
            'material_id' => 1, // Diesel
            'quantity' => 40,
            'unit_price' => 85.50,
            'total_price' => 3420, // 40 * 85.50
        ]);

        // Create diesel ledger entry (debit)
        $dieselLedger = MachineryLedgerService::createDebit([
            'machinery_id' => $this->complexRentalMachine->id,
            'amount' => 3420,
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => $consumptionMaster->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Diesel consumption: 40L',
        ]);

        // Verify diesel ledger entry
        $this->assertDatabaseHas('machinery_ledger', [
            'id' => $dieselLedger->id,
            'machinery_id' => $this->complexRentalMachine->id,
            'entry_direction' => 'debit',
            'entry_type' => 'diesel',
            'amount' => 3420,
        ]);

        // For complex rental with diesel_by_company=true, this should be charged to company
        // (The actual charging logic would depend on business requirements)
    }

    /** @test */
    public function it_prevents_edits_after_financial_posting()
    {
        // Create and approve DPR
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 8,
            'calculated_amount' => 8000,
            'status' => 'approved'
        ]);

        // Create ledger entry
        $ledgerEntry = MachineryLedgerService::createCredit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => $dpr->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'date' => $dpr->date,
        ]);

        // Link DPR to ledger entry
        $dpr->update(['ledger_entry_id' => $ledgerEntry->id]);

        // Try to edit DPR after financial posting
        $response = $this->put("/daily-progress-reports/{$dpr->id}", [
            'work_details' => 'Updated details'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['Cannot edit DPR after ledger entry has been created']);
    }
}
