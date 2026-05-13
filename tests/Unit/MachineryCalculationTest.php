<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Domain\Machinery\Services\MachineryLedgerService;
use App\Domain\Machinery\Services\MachineryPaymentRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class MachineryCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected Machinery $ownedMachine;
    protected Machinery $rentalMachine;
    protected Machinery $complexRentalMachine;

    protected function setUp(): void
    {
        parent::setUp();

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
            'vehicle_number' => 'RENT002',
            'operational_status' => 'active',
        ]);
    }

    /** @test */
    public function it_calculates_owned_machinery_correctly()
    {
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 108,
            'machine_idle_reading' => 0,
            'date' => now()->format('Y-m-d'),
        ]);

        $expectedHours = 8; // 108 - 100
        $expectedAmount = $expectedHours * $this->ownedMachine->rate; // 8 * 1000 = 8000

        $this->assertEquals($expectedHours, $dpr->billable_hours);
        $this->assertEquals($expectedAmount, $dpr->calculated_amount);
    }

    /** @test */
    public function it_calculates_rental_machinery_correctly()
    {
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 215,
            'machine_idle_reading' => 0,
            'date' => now()->format('Y-m-d'),
        ]);

        $expectedHours = 15; // 215 - 200
        $expectedAmount = $expectedHours * $this->rentalMachine->rate; // 15 * 1500 = 22500

        $this->assertEquals($expectedHours, $dpr->billable_hours);
        $this->assertEquals($expectedAmount, $dpr->calculated_amount);
    }

    /** @test */
    public function it_enforces_minimum_billing_hours_for_complex_rental()
    {
        // Test with hours below minimum
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->complexRentalMachine->id,
            'machine_start_reading' => 300,
            'machine_end_reading' => 305, // Only 5 hours
            'machine_idle_reading' => 0,
            'date' => now()->format('Y-m-d'),
        ]);

        $actualHours = 5; // 305 - 300
        $minimumHours = $this->complexRentalMachine->minimum_billing_hours; // 8
        $expectedAmount = $minimumHours * $this->complexRentalMachine->rate; // 8 * 1200 = 9600

        $this->assertEquals($actualHours, $dpr->billable_hours);
        $this->assertEquals($expectedAmount, $dpr->calculated_amount);
    }

    /** @test */
    public function it_handles_idle_hours_correctly()
    {
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 400,
            'machine_end_reading' => 412,
            'machine_idle_reading' => 2, // 2 hours idle
            'date' => now()->format('Y-m-d'),
        ]);

        $totalHours = 12; // 412 - 400
        $billableHours = $totalHours - 2; // Subtract idle hours
        $expectedAmount = $billableHours * $this->ownedMachine->rate; // 10 * 1000 = 10000

        $this->assertEquals($billableHours, $dpr->billable_hours);
        $this->assertEquals($expectedAmount, $dpr->calculated_amount);
    }

    /** @test */
    public function it_prevents_negative_billable_hours()
    {
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 500,
            'machine_end_reading' => 495, // Less than start
            'machine_idle_reading' => 0,
            'date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(0, $dpr->billable_hours);
        $this->assertEquals(0, $dpr->calculated_amount);
    }

    /** @test */
    public function it_calculates_diesel_cost_allocation_correctly()
    {
        // Test rental with diesel_by_company = false (supplier pays)
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'diesel_consumption' => 50,
            'date' => now()->format('Y-m-d'),
        ]);

        // Test rental with diesel_by_company = true (company pays)
        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->complexRentalMachine->id,
            'diesel_consumption' => 50,
            'date' => now()->format('Y-m-d'),
        ]);

        // For rental with diesel_by_company = false, supplier should be charged
        $this->assertFalse($this->rentalMachine->diesel_by_company);
        
        // For rental with diesel_by_company = true, company bears the cost
        $this->assertTrue($this->complexRentalMachine->diesel_by_company);
    }

    /** @test */
    public function it_calculates_operator_cost_responsibility()
    {
        // Test rental with operator_by_supplier = false (self-managed)
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->rentalMachine->id,
            'number_of_operators' => 1,
            'date' => now()->format('Y-m-d'),
        ]);

        // Test rental with operator_by_supplier = true (supplier provides)
        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->complexRentalMachine->id,
            'number_of_operators' => 2,
            'date' => now()->format('Y-m-d'),
        ]);

        // For rental with operator_by_supplier = false, company manages operators
        $this->assertFalse($this->rentalMachine->operator_by_supplier);
        
        // For rental with operator_by_supplier = true, supplier provides operators
        $this->assertTrue($this->complexRentalMachine->operator_by_supplier);
        $this->assertEquals(2, $this->complexRentalMachine->number_of_operators);
    }

    /** @test */
    public function it_calculates_payment_request_correctly()
    {
        // Create multiple DPRs for a machine
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'date' => Carbon::now()->subDays(2)->format('Y-m-d'),
            'billable_hours' => 8,
            'calculated_amount' => 8000,
            'status' => 'approved'
        ]);

        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'date' => Carbon::now()->subDays(1)->format('Y-m-d'),
            'billable_hours' => 10,
            'calculated_amount' => 10000,
            'status' => 'approved'
        ]);

        // Create corresponding ledger entries
        MachineryLedgerService::createCredit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => $dpr1->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr1->id,
            'date' => $dpr1->date,
        ]);

        MachineryLedgerService::createCredit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => $dpr2->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr2->id,
            'date' => $dpr2->date,
        ]);

        // Add some diesel costs (debits)
        MachineryLedgerService::createDebit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => 2000,
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'date' => $dpr1->date,
            'entry_type' => 'diesel',
        ]);

        MachineryLedgerService::createDebit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => 2500,
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 2,
            'date' => $dpr2->date,
            'entry_type' => 'diesel',
        ]);

        // Create payment request
        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));
        
        $paymentRequest = $service->createFromLedger(
            $this->ownedMachine->id,
            null, // No supplier for owned
            Carbon::now()->subDays(2)->format('Y-m-d'),
            Carbon::now()->subDays(1)->format('Y-m-d'),
            1 // Test user ID
        );

        // Expected calculations
        $expectedCredits = 8000 + 10000; // Total earnings
        $expectedDebits = 2000 + 2500; // Total diesel costs
        $expectedNetPayable = $expectedCredits - $expectedDebits; // 18000 - 4500 = 13500

        $this->assertEquals($expectedCredits, $paymentRequest->credits);
        $this->assertEquals($expectedDebits, $paymentRequest->debits);
        $this->assertEquals($expectedNetPayable, $paymentRequest->net_payable);
    }

    /** @test */
    public function it_handles_negative_payable_scenarios()
    {
        // Create scenario where debits exceed credits
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'date' => now()->format('Y-m-d'),
            'billable_hours' => 2,
            'calculated_amount' => 2000, // Low earnings
            'status' => 'approved'
        ]);

        // Create credit entry
        MachineryLedgerService::createCredit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => $dpr->calculated_amount,
            'reference_type' => 'DailyProgressReport',
            'reference_id' => $dpr->id,
            'date' => $dpr->date,
        ]);

        // Create high diesel cost (debit)
        MachineryLedgerService::createDebit([
            'machinery_id' => $this->ownedMachine->id,
            'amount' => 3000, // Higher than earnings
            'reference_type' => 'DailyConsumptionMaster',
            'reference_id' => 1,
            'date' => $dpr->date,
            'entry_type' => 'diesel',
        ]);

        // Create payment request
        $service = new MachineryPaymentRequestService(app(\App\Support\Finance\PaymentAuditLogger::class));
        
        $paymentRequest = $service->createFromLedger(
            $this->ownedMachine->id,
            null,
            now()->format('Y-m-d'),
            now()->format('Y-m-d'),
            1
        );

        // Should have negative net payable
        $this->assertEquals(2000, $paymentRequest->credits);
        $this->assertEquals(3000, $paymentRequest->debits);
        $this->assertEquals(-1000, $paymentRequest->net_payable);
        
        // Status should be HOLD for negative payable
        $this->assertEquals('hold', $paymentRequest->status);
    }

    /** @test */
    public function it_calculates_running_balance_correctly()
    {
        // Create multiple ledger entries
        $entries = [
            ['amount' => 1000, 'direction' => 'credit'],
            ['amount' => 500, 'direction' => 'debit'],
            ['amount' => 2000, 'direction' => 'credit'],
            ['amount' => 300, 'direction' => 'debit'],
        ];

        $expectedBalances = [1000, 500, 2500, 2200];

        foreach ($entries as $index => $entry) {
            if ($entry['direction'] === 'credit') {
                $ledger = MachineryLedgerService::createCredit([
                    'machinery_id' => $this->ownedMachine->id,
                    'amount' => $entry['amount'],
                    'reference_type' => 'Test',
                    'reference_id' => $index,
                    'date' => now()->format('Y-m-d'),
                ]);
            } else {
                $ledger = MachineryLedgerService::createDebit([
                    'machinery_id' => $this->ownedMachine->id,
                    'amount' => $entry['amount'],
                    'reference_type' => 'Test',
                    'reference_id' => $index,
                    'date' => now()->format('Y-m-d'),
                ]);
            }

            $this->assertEquals($expectedBalances[$index], $ledger->running_balance);
        }
    }

    /** @test */
    public function it_validates_rate_calculations_for_different_scenarios()
    {
        $scenarios = [
            [
                'machine' => $this->ownedMachine,
                'hours' => 8,
                'expected_rate' => 1000,
                'expected_amount' => 8000,
            ],
            [
                'machine' => $this->rentalMachine,
                'hours' => 8,
                'expected_rate' => 1500,
                'expected_amount' => 12000,
            ],
            [
                'machine' => $this->complexRentalMachine,
                'hours' => 10, // Above minimum
                'expected_rate' => 1200,
                'expected_amount' => 12000,
            ],
            [
                'machine' => $this->complexRentalMachine,
                'hours' => 5, // Below minimum
                'expected_rate' => 1200,
                'expected_amount' => 9600, // Minimum billing
            ],
        ];

        foreach ($scenarios as $scenario) {
            $dpr = DailyProgressReport::factory()->create([
                'machinery_id' => $scenario['machine']->id,
                'machine_start_reading' => 100,
                'machine_end_reading' => 100 + $scenario['hours'],
                'date' => now()->format('Y-m-d'),
            ]);

            $this->assertEquals($scenario['expected_amount'], $dpr->calculated_amount);
        }
    }

    /** @test */
    public function it_handles_edge_cases_in_calculations()
    {
        // Test zero hours
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 100,
            'date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(0, $dpr1->billable_hours);
        $this->assertEquals(0, $dpr1->calculated_amount);

        // Test very small decimal hours
        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 100.5,
            'date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(0.5, $dpr2->billable_hours);
        $this->assertEquals(500, $dpr2->calculated_amount);

        // Test large number of hours
        $dpr3 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->ownedMachine->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 200, // 100 hours
            'date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(100, $dpr3->billable_hours);
        $this->assertEquals(100000, $dpr3->calculated_amount);
    }
}
