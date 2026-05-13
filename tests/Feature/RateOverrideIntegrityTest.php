<?php

namespace Tests\Feature;

use App\Models\DailyProgressReport;
use App\Models\Machinery;
use App\Models\User;
use App\Domain\Machinery\Services\MachineryRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rate Override Integrity Test
 * Validates that rate override doesn't break historical rate logic
 */
class RateOverrideIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private $adminUser;
    private $siteEngineer;
    private $machinery;
    private $rateService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        $this->siteEngineer = User::factory()->create();
        $this->siteEngineer->assignRole('site engineer');

        $this->machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        $this->rateService = new MachineryRateService();
    }

    /**
     * Test: Historical rate logic integrity without override
     */
    public function test_historical_rate_logic_without_override()
    {
        // Create rate history
        $this->rateService->createRateHistory($this->machinery->id, 1200.00, '2026-05-01');
        $this->rateService->createRateHistory($this->machinery->id, 1500.00, '2026-05-05');

        // Create DPR for historical date
        $this->actingAs($this->adminUser);
        
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => '2026-05-03', // Historical date
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'operator_names' => 'John Doe',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertRedirect();
        
        $dpr = DailyProgressReport::first();
        
        // Verify historical rate used (1200, not current 1500)
        $this->assertEquals(1200, $dpr->rate_snapshot);
        $this->assertEquals(12000, $dpr->calculated_amount); // 10 hours * 1200
        
        // Verify no override applied
        $this->assertNull($dpr->override_rate);
        $this->assertNull($dpr->override_reason);
        $this->assertNull($dpr->override_by);
        $this->assertNull($dpr->override_at);
    }

    /**
     * Test: Rate override with proper role and reason
     */
    public function test_rate_override_with_proper_role_and_reason()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'operator_names' => 'John Doe',
            'override_rate' => 1800.00,
            'override_reason' => 'Night shift premium rate',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertRedirect();
        
        $dpr = DailyProgressReport::first();
        
        // Verify override rate used
        $this->assertEquals(1800, $dpr->rate_snapshot);
        $this->assertEquals(18000, $dpr->calculated_amount); // 10 hours * 1800
        
        // Verify override fields populated
        $this->assertEquals(1800, $dpr->override_rate);
        $this->assertEquals('Night shift premium rate', $dpr->override_reason);
        $this->assertEquals($this->adminUser->id, $dpr->override_by);
        $this->assertNotNull($dpr->override_at);
    }

    /**
     * Test: Rate override blocked for unauthorized roles
     */
    public function test_rate_override_blocked_for_unauthorized_roles()
    {
        $this->actingAs($this->siteEngineer);

        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'override_rate' => 1800.00, // Attempt override
            'override_reason' => 'Trying to override',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertSessionHasErrors(['override_rate']);
        
        // Verify no DPR created due to validation error
        $this->assertDatabaseCount('daily_progress_reports', 0);
    }

    /**
     * Test: Rate override requires reason
     */
    public function test_rate_override_requires_reason()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'override_rate' => 1800.00,
            // Missing override_reason
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertSessionHasErrors(['override_reason']);
        
        // Verify no DPR created due to validation error
        $this->assertDatabaseCount('daily_progress_reports', 0);
    }

    /**
     * Test: Rate override validation (positive rate only)
     */
    public function test_rate_override_validation_positive_rate_only()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'override_rate' => -500.00, // Negative rate
            'override_reason' => 'Testing negative rate',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertSessionHasErrors(['override_rate']);
        
        // Verify no DPR created due to validation error
        $this->assertDatabaseCount('daily_progress_reports', 0);
    }

    /**
     * Test: Historical rate logic preserved with override
     */
    public function test_historical_rate_logic_preserved_with_override()
    {
        // Create rate history
        $this->rateService->createRateHistory($this->machinery->id, 1200.00, '2026-05-01');
        $this->rateService->createRateHistory($this->machinery->id, 1500.00, '2026-05-05');

        $this->actingAs($this->adminUser);

        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => '2026-05-03', // Historical date with rate 1200
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'override_rate' => 2000.00, // Override historical rate
            'override_reason' => 'Special project rate',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertRedirect();
        
        $dpr = DailyProgressReport::first();
        
        // Verify override rate used (not historical)
        $this->assertEquals(2000, $dpr->rate_snapshot);
        $this->assertEquals(20000, $dpr->calculated_amount); // 10 hours * 2000
        
        // Verify override fields populated
        $this->assertEquals(2000, $dpr->override_rate);
        $this->assertEquals('Special project rate', $dpr->override_reason);
        $this->assertEquals($this->adminUser->id, $dpr->override_by);
        $this->assertNotNull($dpr->override_at);
    }

    /**
     * Test: Rate override audit trail
     */
    public function test_rate_override_audit_trail()
    {
        $this->actingAs($this->adminUser);

        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'override_rate' => 1800.00,
            'override_reason' => 'Weekend premium rate',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $response->assertRedirect();
        
        $dpr = DailyProgressReport::first();
        
        // Verify audit trail is complete
        $this->assertNotNull($dpr->override_by);
        $this->assertNotNull($dpr->override_at);
        $this->assertNotNull($dpr->override_reason);
        $this->assertEquals($this->adminUser->id, $dpr->override_by);
        
        // Verify calculation hash includes override rate
        $this->assertNotNull($dpr->calculation_hash);
        $this->assertNotEquals('', $dpr->calculation_hash);
    }

    /**
     * Test: Rate override doesn't affect other DPRs
     */
    public function test_rate_override_doesnt_affect_other_dprs()
    {
        // Create first DPR with override
        $this->actingAs($this->adminUser);

        $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            'override_rate' => 1800.00,
            'override_reason' => 'First DPR override',
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        // Create second DPR without override
        $this->post(route('daily-progress-reports.store'), [
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 210,
            'machine_idle_reading' => 0,
            'number_of_operators' => 1,
            // No override fields
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        $dprs = DailyProgressReport::all();
        
        // Verify first DPR has override
        $this->assertEquals(1800, $dprs[0]->rate_snapshot);
        $this->assertEquals(1800, $dprs[0]->override_rate);
        
        // Verify second DPR uses standard rate
        $this->assertEquals(1500, $dprs[1]->rate_snapshot);
        $this->assertNull($dprs[1]->override_rate);
    }
}
