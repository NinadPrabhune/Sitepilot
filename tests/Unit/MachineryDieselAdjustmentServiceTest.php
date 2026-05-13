<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\DailyConsumptionMaster;
use App\Models\DailyProgressReport;
use App\Services\MachineryDieselAdjustmentService;
use Carbon\Carbon;

class MachineryDieselAdjustmentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test machinery
        $this->companyDieselMachinery = Machinery::factory()->create([
            'diesel_by_company' => true,
            'owned_by' => 'rental'
        ]);
        
        $this->supplierDieselMachinery = Machinery::factory()->create([
            'diesel_by_company' => false,
            'owned_by' => 'rental'
        ]);
    }

    /** @test */
    public function it_calculates_diesel_deduction_for_company_diesel_machinery()
    {
        // Create test DPRs
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->companyDieselMachinery->id,
            'date' => '2026-05-10'
        ]);
        
        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->companyDieselMachinery->id,
            'date' => '2026-05-11'
        ]);

        // Create diesel consumption entries
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr1->id,
            'consumption_date' => '2026-05-10',
            'diesel_consumed_liters' => 40,
            'diesel_rate' => 90,
            'diesel_total_cost' => 3600
        ]);
        
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr2->id,
            'consumption_date' => '2026-05-11',
            'diesel_consumed_liters' => 35,
            'diesel_rate' => 92,
            'diesel_total_cost' => 3220
        ]);

        $from = Carbon::create(2026, 5, 10);
        $to = Carbon::create(2026, 5, 11);

        $result = MachineryDieselAdjustmentService::calculateDieselDeduction($this->companyDieselMachinery, $from, $to);

        $this->assertEquals(75, $result['total_liters']); // 40 + 35
        $this->assertEquals(6820, $result['total_cost']); // 3600 + 3220
        $this->assertTrue($result['applicable_for_deduction']);
        $this->assertEquals('company', $result['diesel_responsibility']);
        $this->assertCount(2, $result['entries']);
    }

    /** @test */
    public function it_calculates_diesel_deduction_for_supplier_diesel_machinery()
    {
        // Create test DPR
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->supplierDieselMachinery->id,
            'date' => '2026-05-10'
        ]);

        // Create diesel consumption entry
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr->id,
            'consumption_date' => '2026-05-10',
            'diesel_consumed_liters' => 40,
            'diesel_rate' => 90,
            'diesel_total_cost' => 3600
        ]);

        $from = Carbon::create(2026, 5, 10);
        $to = Carbon::create(2026, 5, 10);

        $result = MachineryDieselAdjustmentService::calculateDieselDeduction($this->supplierDieselMachinery, $from, $to);

        $this->assertEquals(40, $result['total_liters']);
        $this->assertEquals(3600, $result['total_cost']);
        $this->assertFalse($result['applicable_for_deduction']); // Supplier pays diesel
        $this->assertEquals('supplier', $result['diesel_responsibility']);
    }

    /** @test */
    public function it_handles_missing_diesel_rate_with_default_rate()
    {
        // Create test DPR
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->companyDieselMachinery->id,
            'date' => '2026-05-10'
        ]);

        // Create diesel consumption without rate (old data)
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr->id,
            'consumption_date' => '2026-05-10',
            'diesel_consumed_liters' => 40,
            'diesel_rate' => null, // Missing rate
            'diesel_total_cost' => 0 // No cost calculated
        ]);

        $from = Carbon::create(2026, 5, 10);
        $to = Carbon::create(2026, 5, 10);

        $result = MachineryDieselAdjustmentService::calculateDieselDeduction($this->companyDieselMachinery, $from, $to);

        $expectedCost = 40 * 90; // Default rate
        $this->assertEquals($expectedCost, $result['total_cost']);
    }

    /** @test */
    public function it_validates_diesel_entry_correctly()
    {
        // Valid entry
        $validData = [
            'diesel_consumed_liters' => 40,
            'diesel_rate' => 90,
            'daily_progress_report_id' => 1,
            'machinery_id' => 1
        ];

        $result = MachineryDieselAdjustmentService::validateDieselEntry($validData);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);

        // Invalid: negative consumption
        $invalidData = [
            'diesel_consumed_liters' => -10,
            'diesel_rate' => 90
        ];

        $result = MachineryDieselAdjustmentService::validateDieselEntry($invalidData);
        $this->assertFalse($result['valid']);
        $this->assertContains('Diesel consumption must be greater than 0', $result['errors']);

        // Invalid: excessive consumption
        $excessiveData = [
            'diesel_consumed_liters' => 1500,
            'diesel_rate' => 90
        ];

        $result = MachineryDieselAdjustmentService::validateDieselEntry($excessiveData);
        $this->assertFalse($result['valid']);
        $this->assertContains('Diesel consumption seems excessive (>1000 liters)', $result['errors']);
    }

    /** @test */
    public function it_calculates_single_entry_cost()
    {
        $data = [
            'diesel_consumed_liters' => 40,
            'diesel_rate' => 92
        ];

        $cost = MachineryDieselAdjustmentService::calculateEntryCost($data);
        $this->assertEquals(3680, $cost); // 40 * 92

        // Test with default rate
        $dataWithoutRate = [
            'diesel_consumed_liters' => 40
        ];

        $cost = MachineryDieselAdjustmentService::calculateEntryCost($dataWithoutRate);
        $this->assertEquals(3600, $cost); // 40 * 90 (default rate)
    }

    /** @test */
    public function it_updates_diesel_entry_with_frozen_rate()
    {
        $entry = DailyConsumptionMaster::factory()->create([
            'diesel_consumed_liters' => 40,
            'diesel_rate' => null,
            'diesel_total_cost' => 0
        ]);

        $rate = 95;
        MachineryDieselAdjustmentService::updateDieselEntryWithFrozenRate($entry, $rate);

        $entry->refresh();
        $this->assertEquals(95, $entry->diesel_rate);
        $this->assertEquals(3800, $entry->diesel_total_cost); // 40 * 95
    }

    /** @test */
    public function it_gets_diesel_consumption_summary()
    {
        // Create test DPRs
        $dpr1 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->companyDieselMachinery->id,
            'date' => '2026-05-10'
        ]);
        
        $dpr2 = DailyProgressReport::factory()->create([
            'machinery_id' => $this->companyDieselMachinery->id,
            'date' => '2026-05-11'
        ]);

        // Create diesel consumption entries
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr1->id,
            'consumption_date' => '2026-05-10',
            'diesel_consumed_liters' => 40,
            'diesel_rate' => 90,
            'diesel_total_cost' => 3600
        ]);
        
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr2->id,
            'consumption_date' => '2026-05-11',
            'diesel_consumed_liters' => 30,
            'diesel_rate' => 100,
            'diesel_total_cost' => 3000
        ]);

        $from = Carbon::create(2026, 5, 10);
        $to = Carbon::create(2026, 5, 11);

        $summary = MachineryDieselAdjustmentService::getDieselConsumptionSummary($this->companyDieselMachinery, $from, $to);

        $this->assertEquals(70, $summary['total_liters']); // 40 + 30
        $this->assertEquals(6600, $summary['total_cost']); // 3600 + 3000
        $this->assertEquals(94.29, round($summary['average_rate'], 2)); // 6600 / 70
        $this->assertEquals(2, $summary['entry_count']);
        $this->assertEquals(35, $summary['daily_average']); // 70 / 2
    }

    /** @test */
    public function it_handles_empty_period_gracefully()
    {
        $from = Carbon::create(2026, 5, 1);
        $to = Carbon::create(2026, 5, 5);

        $result = MachineryDieselAdjustmentService::calculateDieselDeduction($this->companyDieselMachinery, $from, $to);

        $this->assertEquals(0, $result['total_liters']);
        $this->assertEquals(0, $result['total_cost']);
        $this->assertTrue($result['applicable_for_deduction']);
        $this->assertEmpty($result['entries']);
    }

    /** @test */
    public function it_provides_detailed_entry_breakdown()
    {
        // Create test DPR
        $dpr = DailyProgressReport::factory()->create([
            'machinery_id' => $this->companyDieselMachinery->id,
            'date' => '2026-05-10'
        ]);

        // Create diesel consumption entry
        DailyConsumptionMaster::factory()->create([
            'daily_progress_report_id' => $dpr->id,
            'consumption_date' => '2026-05-10',
            'diesel_consumed_liters' => 45,
            'diesel_rate' => 88,
            'diesel_total_cost' => 3960
        ]);

        $from = Carbon::create(2026, 5, 10);
        $to = Carbon::create(2026, 5, 10);

        $result = MachineryDieselAdjustmentService::calculateDieselDeduction($this->companyDieselMachinery, $from, $to);

        $entries = $result['entries'];
        $this->assertCount(1, $entries);
        
        $entry = $entries[0];
        $this->assertEquals('2026-05-10', $entry['date']);
        $this->assertEquals(45, $entry['liters']);
        $this->assertEquals(88, $entry['rate']);
        $this->assertEquals(3960, $entry['amount']);
    }
}
