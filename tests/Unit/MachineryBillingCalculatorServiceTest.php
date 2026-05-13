<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Services\MachineryBillingCalculatorService;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class MachineryBillingCalculatorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test machinery
        $this->hourlyMachinery = Machinery::factory()->create([
            'rate_type' => 'hourly',
            'rate' => 500,
            'owned_by' => 'rental'
        ]);
        
        $this->dailyMachinery = Machinery::factory()->create([
            'rate_type' => 'daily',
            'rate' => 5000,
            'owned_by' => 'rental'
        ]);
        
        $this->monthlyMachinery = Machinery::factory()->create([
            'rate_type' => 'monthly',
            'rate' => 50000,
            'owned_by' => 'rental'
        ]);
    }

    /** @test */
    public function it_calculates_hourly_billing_correctly()
    {
        $dprs = collect([
            DailyProgressReport::factory()->make(['billable_hours' => 8]),
            DailyProgressReport::factory()->make(['billable_hours' => 6]),
            DailyProgressReport::factory()->make(['billable_hours' => 0])
        ]);

        $from = now()->subDays(3);
        $to = now();

        $result = MachineryBillingCalculatorService::calculate($this->hourlyMachinery, $dprs, $from, $to);

        $this->assertEquals(7000, $result['gross_amount']); // (8+6+0) * 500
        $this->assertEquals(14, $result['total_hours']);
        $this->assertEquals(500, $result['rate_applied']);
        $this->assertEquals('hourly', $result['calculation_type']);
        $this->assertCount(3, $result['hourly_breakdown']);
    }

    /** @test */
    public function it_calculates_daily_billing_any_usage_counts_full_day()
    {
        $dprs = collect([
            DailyProgressReport::factory()->make(['billable_hours' => 0.5]), // 30 mins
            DailyProgressReport::factory()->make(['billable_hours' => 8]),   // Full day
            DailyProgressReport::factory()->make(['billable_hours' => 0])    // No usage
        ]);

        $from = now()->subDays(3);
        $to = now();

        $result = MachineryBillingCalculatorService::calculate($this->dailyMachinery, $dprs, $from, $to);

        // 2 working days × 5000 (any usage counts as full day)
        $this->assertEquals(10000, $result['gross_amount']);
        $this->assertEquals(2, $result['working_days']);
        $this->assertEquals(5000, $result['rate_applied']);
        $this->assertEquals('daily', $result['calculation_type']);
        $this->assertCount(3, $result['daily_breakdown']);
        
        // Check breakdown
        $breakdown = $result['daily_breakdown'];
        $this->assertEquals(5000, $breakdown[0]['charged']); // 0.5 hours = full day
        $this->assertEquals(5000, $breakdown[1]['charged']); // 8 hours = full day
        $this->assertEquals(0, $breakdown[2]['charged']);    // 0 hours = no charge
    }

    /** @test */
    public function it_calculates_monthly_prorated_billing()
    {
        $dprs = collect([
            DailyProgressReport::factory()->make(['billable_hours' => 8]),
            DailyProgressReport::factory()->make(['billable_hours' => 6])
        ]);

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        $result = MachineryBillingCalculatorService::calculate($this->monthlyMachinery, $dprs, $from, $to);

        $totalDaysInMonth = $from->daysInMonth;
        $activeDays = 2; // 2 DPRs with >0 hours
        $dailyRate = $this->monthlyMachinery->rate / $totalDaysInMonth;
        $expectedGross = $dailyRate * $activeDays;

        $this->assertEquals($expectedGross, $result['gross_amount']);
        $this->assertEquals(2, $result['active_days']);
        $this->assertEquals($totalDaysInMonth, $result['total_days_in_month']);
        $this->assertEquals($dailyRate, $result['daily_rate']);
        $this->assertEquals('monthly_prorated', $result['calculation_type']);
    }

    /** @test */
    public function it_handles_empty_dpr_collection()
    {
        $dprs = collect([]);
        $from = now()->subDays(7);
        $to = now();

        $result = MachineryBillingCalculatorService::calculate($this->hourlyMachinery, $dprs, $from, $to);

        $this->assertEquals(0, $result['gross_amount']);
        $this->assertEquals(0, $result['total_hours']);
    }

    /** @test */
    public function it_throws_exception_for_invalid_rate_type()
    {
        $invalidMachinery = Machinery::factory()->make(['rate_type' => 'invalid']);
        $dprs = collect([]);
        $from = now()->subDays(7);
        $to = now();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid rate_type: invalid');

        MachineryBillingCalculatorService::calculate($invalidMachinery, $dprs, $from, $to);
    }

    /** @test */
    public function it_calculates_single_dpr_amount_hourly()
    {
        $billableHours = 8;
        $result = MachineryBillingCalculatorService::calculateDprAmount($this->hourlyMachinery, $billableHours);

        $this->assertEquals(4000, $result); // 8 * 500
    }

    /** @test */
    public function it_calculates_single_dpr_amount_daily()
    {
        $billableHours = 2;
        $result = MachineryBillingCalculatorService::calculateDprAmount($this->dailyMachinery, $billableHours);

        $this->assertEquals(5000, $result); // Any usage = full day
    }

    /** @test */
    public function it_calculates_single_dpr_amount_daily_zero_hours()
    {
        $billableHours = 0;
        $result = MachineryBillingCalculatorService::calculateDprAmount($this->dailyMachinery, $billableHours);

        $this->assertEquals(0, $result); // No usage = no charge
    }

    /** @test */
    public function it_calculates_single_dpr_amount_monthly()
    {
        $billableHours = 8;
        $result = MachineryBillingCalculatorService::calculateDprAmount($this->monthlyMachinery, $billableHours);

        $this->assertEquals(0, $result); // Monthly handled at payment request level
    }

    /** @test */
    public function it_validates_rate_type()
    {
        $this->assertTrue(MachineryBillingCalculatorService::validateRateType('hourly'));
        $this->assertTrue(MachineryBillingCalculatorService::validateRateType('daily'));
        $this->assertTrue(MachineryBillingCalculatorService::validateRateType('monthly'));
        $this->assertFalse(MachineryBillingCalculatorService::validateRateType('invalid'));
        $this->assertFalse(MachineryBillingCalculatorService::validateRateType(''));
    }

    /** @test */
    public function it_handles_edge_cases_for_daily_billing()
    {
        $dprs = collect([
            DailyProgressReport::factory()->make(['billable_hours' => 0.1]), // Minimal usage
            DailyProgressReport::factory()->make(['billable_hours' => 24]),  // Full day
            DailyProgressReport::factory()->make(['billable_hours' => 12]),  // Half day
            DailyProgressReport::factory()->make(['billable_hours' => 0.01]) // Tiny usage
        ]);

        $from = now()->subDays(4);
        $to = now();

        $result = MachineryBillingCalculatorService::calculate($this->dailyMachinery, $dprs, $from, $to);

        // All non-zero hours should count as full day
        $this->assertEquals(20000, $result['gross_amount']); // 4 days × 5000
        $this->assertEquals(4, $result['working_days']);
    }

    /** @test */
    public function it_handles_month_end_edge_cases()
    {
        // Test February (short month)
        $febFrom = Carbon::create(2026, 2, 1);
        $febTo = Carbon::create(2026, 2, 28);
        
        $dprs = collect([
            DailyProgressReport::factory()->make(['billable_hours' => 8]),
            DailyProgressReport::factory()->make(['billable_hours' => 6])
        ]);

        $result = MachineryBillingCalculatorService::calculate($this->monthlyMachinery, $dprs, $febFrom, $febTo);

        $expectedDailyRate = $this->monthlyMachinery->rate / 28; // February has 28 days
        $expectedGross = $expectedDailyRate * 2; // 2 active days

        $this->assertEquals($expectedGross, $result['gross_amount']);
        $this->assertEquals(28, $result['total_days_in_month']);
    }

    /** @test */
    public function it_provides_detailed_breakdown_for_hourly_billing()
    {
        $dprs = collect([
            DailyProgressReport::factory()->make(['billable_hours' => 5, 'date' => '2026-05-10']),
            DailyProgressReport::factory()->make(['billable_hours' => 7, 'date' => '2026-05-11'])
        ]);

        $from = Carbon::create(2026, 5, 10);
        $to = Carbon::create(2026, 5, 11);

        $result = MachineryBillingCalculatorService::calculate($this->hourlyMachinery, $dprs, $from, $to);

        $breakdown = $result['hourly_breakdown'];
        
        $this->assertCount(2, $breakdown);
        $this->assertEquals('2026-05-10', $breakdown[0]['date']);
        $this->assertEquals(5, $breakdown[0]['billable_hours']);
        $this->assertEquals(2500, $breakdown[0]['amount']); // 5 * 500
        
        $this->assertEquals('2026-05-11', $breakdown[1]['date']);
        $this->assertEquals(7, $breakdown[1]['billable_hours']);
        $this->assertEquals(3500, $breakdown[1]['amount']); // 7 * 500
    }
}
