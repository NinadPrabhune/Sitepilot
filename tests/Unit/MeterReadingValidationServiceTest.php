<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Services\MeterReadingValidationService;
use Carbon\Carbon;

class MeterReadingValidationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->machinery = Machinery::factory()->create();
    }

    /** @test */
    public function it_validates_correct_meter_reading()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8,
            'number_of_operators' => 2
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_rejects_end_reading_less_than_start_reading()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 950, // Less than start
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('End reading cannot be less than start reading', $result['errors']);
    }

    /** @test */
    public function it_rejects_billable_hours_exceeding_24()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1250,
            'billable_hours' => 25 // More than 24
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Billable hours cannot exceed 24 hours per day', $result['errors']);
    }

    /** @test */
    public function it_rejects_negative_billable_hours()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => -5
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Billable hours cannot be negative', $result['errors']);
    }

    /** @test */
    public function it_validates_against_previous_days_reading()
    {
        // Create previous day's DPR
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-09',
            'machine_end_reading' => 1200
        ]);

        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1150, // Less than previous day's end reading
            'machine_end_reading' => 1250,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains("Start reading cannot be less than previous day's reading (1200)", $result['errors']);
    }

    /** @test */
    public function it_rejects_end_reading_less_than_previous_days_end_reading()
    {
        // Create previous day's DPR
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-09',
            'machine_end_reading' => 1300
        ]);

        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1250,
            'machine_end_reading' => 1200, // Less than previous day's end reading
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains("End reading cannot be less than previous day's end reading (1300)", $result['errors']);
    }

    /** @test */
    public function it_validates_idle_reading_correctly()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_idle_reading' => 1020,
            'machine_end_reading' => 1080,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_rejects_idle_reading_less_than_start_reading()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_idle_reading' => 950, // Less than start
            'machine_end_reading' => 1080,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Idle reading cannot be less than start reading', $result['errors']);
    }

    /** @test */
    public function it_rejects_idle_reading_greater_than_end_reading()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_idle_reading' => 1150, // Greater than end
            'machine_end_reading' => 1080,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Idle reading cannot be greater than end reading', $result['errors']);
    }

    /** @test */
    public function it_rejects_future_dates()
    {
        $data = [
            'date' => now()->addDay()->toDateString(), // Future date
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Date cannot be in the future', $result['errors']);
    }

    /** @test */
    public function it_warns_about_old_dates()
    {
        $data = [
            'date' => now()->subMonths(4)->toDateString(), // More than 3 months old
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertTrue($result['valid']); // Still valid, but with warning
        $this->assertContains('Date is more than 3 months old. Please verify.', $result['warnings']);
    }

    /** @test */
    public function it_detects_duplicate_dpr()
    {
        // Create existing DPR
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-10'
        ]);

        $data = [
            'date' => '2026-05-10', // Same date as existing DPR
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Daily Progress Report already exists for this machinery on 2026-05-10', $result['errors']);
    }

    /** @test */
    public function it_validates_number_of_operators()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8,
            'number_of_operators' => 15 // High number
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->assertTrue($result['valid']); // Still valid, but with warning
        $this->assertContains('High number of operators detected (>10). Please verify.', $result['warnings']);
    }

    /** @test */
    public function it_rejects_negative_operators()
    {
        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8,
            'number_of_operators' => -5
        ];

        $result = MeterReadingValidationService::validateReading($data, $this->machinery);

        $this->false($result['valid']);
        $this->assertContains('Number of operators cannot be negative', $result['errors']);
    }

    /** @test */
    public function it_calculates_billable_hours_correctly()
    {
        $data = [
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1080,
            'machine_idle_reading' => 1020
        ];

        $billableHours = MeterReadingValidationService::calculateBillableHours($data);
        $this->assertEquals(6, $billableHours); // (1080 - 1000) - (1020 - 1000) = 80 - 20 = 60 = 6 hours
    }

    /** @test */
    public function it_handles_missing_idle_reading()
    {
        $data = [
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1080
            // No idle reading
        ];

        $billableHours = MeterReadingValidationService::calculateBillableHours($data);
        $this->assertEquals(8, $billableHours); // (1080 - 1000) = 80 = 8 hours
    }

    /** @test */
    public function it_gets_previous_days_reading()
    {
        // Create previous day's DPR
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-09',
            'machine_end_reading' => 1250
        ]);

        $date = Carbon::create(2026, 5, 10);
        $previousReading = MeterReadingValidationService::getPreviousDayReading($this->machinery, $date);

        $this->assertEquals(1250, $previousReading);
    }

    /** @test */
    public function it_returns_null_for_no_previous_reading()
    {
        $date = Carbon::create(2026, 5, 10);
        $previousReading = MeterReadingValidationService::getPreviousDayReading($this->machinery, $date);

        $this->assertNull($previousReading);
    }

    /** @test */
    public function it_detects_meter_reading_anomalies()
    {
        // Create DPRs with anomalies
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-08',
            'machine_end_reading' => 1000
        ]);

        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-09',
            'machine_end_reading' => 950 // Negative production
        ]);

        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-10',
            'machine_end_reading' => 2200 // Large jump
        ]);

        $from = Carbon::create(2026, 5, 8);
        $to = Carbon::create(2026, 5, 10);

        $anomalies = MeterReadingValidationService::checkForAnomalies($this->machinery, $from, $to);

        $this->assertCount(2, $anomalies);
        
        // Check negative production anomaly
        $negativeAnomaly = $anomalies->firstWhere('type', 'negative_production');
        $this->assertNotNull($negativeAnomaly);
        $this->assertEquals('error', $negativeAnomaly['severity']);
        $this->assertStringContains('End reading (950) less than previous day\'s reading (1000)', $negativeAnomaly['message']);
        
        // Check large jump anomaly
        $jumpAnomaly = $anomalies->firstWhere('type', 'large_jump');
        $this->assertNotNull($jumpAnomaly);
        $this->assertEquals('warning', $jumpAnomaly['severity']);
        $this->assertStringContains('Large meter jump detected: 1250 units', $jumpAnomaly['message']);
    }

    /** @test */
    public function it_validates_reading_update_with_date_conflict()
    {
        // Create existing DPR
        $existingDPR = DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-10',
            'machine_end_reading' => 1200
        ]);

        // Create conflicting DPR
        DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-11',
            'machine_end_reading' => 1300
        ]);

        $data = [
            'date' => '2026-05-11', // Trying to change to conflicting date
            'machine_start_reading' => 1250,
            'machine_end_reading' => 1350,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReadingUpdate($data, $existingDPR, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Another Daily Progress Report exists for this machinery on 2026-05-11', $result['errors']);
    }

    /** @test */
    public function it_prevents_modification_of_approved_dpr()
    {
        // Create approved DPR
        $approvedDPR = DailyProgressReport::factory()->create([
            'machinery_id' => $this->machinery->id,
            'date' => '2026-05-10',
            'approved_at' => now(),
            'approved_by' => 1
        ]);

        $data = [
            'date' => '2026-05-10',
            'machine_start_reading' => 1000,
            'machine_end_reading' => 1050,
            'billable_hours' => 8
        ];

        $result = MeterReadingValidationService::validateReadingUpdate($data, $approvedDPR, $this->machinery);

        $this->assertFalse($result['valid']);
        $this->assertContains('Cannot modify approved Daily Progress Report', $result['errors']);
    }
}
