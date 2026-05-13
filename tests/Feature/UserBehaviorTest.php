<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Machinery;
use App\Models\DailyProgressReport;
use App\Models\MachineryLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * User Behavior Test
 * Tests real user behavior patterns and system responses
 */
class UserBehaviorTest extends TestCase
{
    use RefreshDatabase;

    private $siteEngineer;
    private $accountsUser;
    private $adminUser;
    private $machinery;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users with different roles
        $this->siteEngineer = User::factory()->create();
        $this->siteEngineer->assignRole('site engineer');

        $this->accountsUser = User::factory()->create();
        $this->accountsUser->assignRole('accounts');

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole('admin');

        // Create test machinery
        $this->machinery = Machinery::create([
            'name' => 'Test Excavator',
            'rate' => 1500.00,
            'minimum_billing_hours' => 8,
            'owned_by' => 'rental',
            'workspace_id' => 1,
            'site_id' => 1,
        ]);
    }

    /**
     * Test: User tries to enter wrong readings
     */
    public function test_user_enters_wrong_readings()
    {
        $this->actingAs($this->siteEngineer);

        // Test 1: End reading less than start reading
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 90, // Wrong: less than start
            'machine_idle_reading' => 0,
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        // Should be rejected by validation
        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('daily_progress_reports', [
            'machine_start_reading' => 100,
            'machine_end_reading' => 90,
        ]);

        // Test 2: Negative idle hours
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => -5, // Wrong: negative
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        // Should be rejected by validation
        $response->assertSessionHasErrors();
    }

    /**
     * Test: User tries to create duplicate entries
     */
    public function test_user_tries_duplicate_entries()
    {
        $this->actingAs($this->siteEngineer);

        // Create first DPR
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->siteEngineer->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Try to create duplicate DPR for same machinery and date
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(), // Same date
            'machinery_id' => $this->machinery->id, // Same machinery
            'machine_start_reading' => 200,
            'machine_end_reading' => 210,
            'machine_idle_reading' => 0,
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        // Should be rejected by overlap prevention
        $response->assertSessionHasErrors(['date']);
        $this->assertEquals(1, DailyProgressReport::where('machinery_id', $this->machinery->id)
                                                ->where('date', now()->toDateString())
                                                ->count());
    }

    /**
     * Test: User tries to edit after approval
     */
    public function test_user_tries_edit_after_approval()
    {
        $this->actingAs($this->siteEngineer);

        // Create DPR
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->siteEngineer->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Lock the DPR (simulate approval)
        $dpr->update([
            'is_locked' => true,
            'locked_by' => $this->accountsUser->id,
            'locked_at' => now(),
        ]);

        // Try to edit locked DPR
        $response = $this->put(route('daily-progress-reports.update', $dpr->id), [
            'machine_end_reading' => 115, // Try to change reading
        ]);

        // Should be rejected by policy
        $response->assertStatus(403);
        
        // Verify DPR unchanged
        $this->assertEquals(110, $dpr->fresh()->machine_end_reading);
    }

    /**
     * Test: User tries to backdate entries
     */
    public function test_user_tries_backdating()
    {
        $this->actingAs($this->siteEngineer);

        // Try to create DPR for a date in locked period
        $lockedDate = now()->subMonth()->toDateString();
        
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => $lockedDate, // Backdated
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        // Should be rejected by period lock validation
        $response->assertSessionHasErrors(['date']);
    }

    /**
     * Test: Unauthorized access attempts
     */
    public function test_unauthorized_access_attempts()
    {
        // Test 1: Site engineer tries to access admin functions
        $this->actingAs($this->siteEngineer);

        $response = $this->get('/admin/users');
        $response->assertStatus(403);

        // Test 2: Regular user tries to delete DPR
        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
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

        $response = $this->delete(route('daily-progress-reports.destroy', $dpr->id));
        $response->assertStatus(403);

        // Test 3: User tries to access DPR from different site
        $differentSiteDpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->adminUser->id,
            'workspace_id' => 1,
            'site_id' => 2, // Different site
        ]);

        $response = $this->get(route('daily-progress-reports.show', $differentSiteDpr->id));
        $response->assertStatus(403);
    }

    /**
     * Test: User confusion points
     */
    public function test_user_confusion_points()
    {
        $this->actingAs($this->siteEngineer);

        // Test 1: User confusion about minimum billing
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 105, // Only 5 hours (below minimum 8)
            'machine_idle_reading' => 0,
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ]);

        // Should succeed but with minimum billing applied
        $response->assertRedirect();
        $dpr = DailyProgressReport::latest()->first();
        
        // Verify minimum billing was applied
        $this->assertEquals(8, $dpr->billable_hours); // Minimum billing hours
        $this->assertEquals(12000, $dpr->calculated_amount); // 8 * 1500

        // Test 2: User confusion about rate changes
        // Change machinery rate
        $this->machinery->update(['rate' => 2000]);

        // Create new DPR
        $newDpr = DailyProgressReport::create([
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 200,
            'machine_end_reading' => 210,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 2000, // Should use new rate
            'billable_hours' => 10,
            'calculated_amount' => 20000,
            'created_by' => $this->siteEngineer->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Verify old DPR rate unchanged
        $this->assertEquals(1500, $dpr->fresh()->rate_snapshot);
        $this->assertEquals(15000, $dpr->fresh()->calculated_amount);
    }

    /**
     * Test: System resilience to user errors
     */
    public function test_system_resilience_to_user_errors()
    {
        $this->actingAs($this->siteEngineer);

        // Test 1: Concurrent DPR creation attempts
        $dprData = [
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'items' => [
                ['material_id' => 1, 'quantity' => 10, 'unit' => 'liters']
            ],
        ];

        // First request should succeed
        $response1 = $this->post(route('daily-progress-reports.store'), $dprData);
        $response1->assertRedirect();

        // Second concurrent request should fail
        $response2 = $this->post(route('daily-progress-reports.store'), $dprData);
        $response2->assertSessionHasErrors();

        // Test 2: Partial data submission
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->machinery->id,
            // Missing required fields
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('daily_progress_reports', [
            'date' => now()->addDay()->toDateString(),
        ]);

        // Test 3: Invalid data types
        $response = $this->post(route('daily-progress-reports.store'), [
            'date' => now()->addDay()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 'not-a-number',
            'machine_end_reading' => 'also-not-a-number',
            'machine_idle_reading' => 'still-not-a-number',
            'items' => [
                ['material_id' => 1, 'quantity' => 'not-a-number', 'unit' => 'liters']
            ],
        ]);

        $response->assertSessionHasErrors();
    }

    /**
     * Test: User role boundary testing
     */
    public function test_user_role_boundary_testing()
    {
        // Test accounts user permissions
        $this->actingAs($this->accountsUser);

        $dpr = DailyProgressReport::create([
            'date' => now()->toDateString(),
            'machinery_id' => $this->machinery->id,
            'machine_start_reading' => 100,
            'machine_end_reading' => 110,
            'machine_idle_reading' => 0,
            'rate_snapshot' => 1500,
            'billable_hours' => 10,
            'calculated_amount' => 15000,
            'created_by' => $this->siteEngineer->id,
            'workspace_id' => 1,
            'site_id' => 1,
        ]);

        // Accounts user should be able to view and edit unlocked DPR
        $response = $this->get(route('daily-progress-reports.show', $dpr->id));
        $response->assertStatus(200);

        $response = $this->get(route('daily-progress-reports.edit', $dpr->id));
        $response->assertStatus(200);

        // But should not be able to delete
        $response = $this->delete(route('daily-progress-reports.destroy', $dpr->id));
        $response->assertStatus(403);

        // Test admin user permissions
        $this->actingAs($this->adminUser);

        // Admin should be able to delete
        $response = $this->delete(route('daily-progress-reports.destroy', $dpr->id));
        $response->assertRedirect();
    }
}
