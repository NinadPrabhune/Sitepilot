<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Machinery;
use App\Models\User;
use App\Models\ChNotification;
use App\Models\ChNotificationUser;
use App\Domain\Machinery\Services\MachineryNotificationService;
use App\Services\NotificationService;
use App\Jobs\CheckMachineryDueDateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Mockery;

class MachineryDueDateNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testUser = User::factory()->create([
            'email' => 'machinery-test@example.com',
            'type' => 'company',
        ]);
    }

    /** @test */
    public function it_can_check_puc_due_today()
    {
        // Create machinery with PUC due today
        $machinery = Machinery::factory()->create([
            'name' => 'Test Excavator',
            'machine_id' => 'MCH-001',
            'vehicle_number' => 'ABC123',
            'status' => 'active',
            'puc_due_date' => now()->toDateString(),
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        // Verify the machinery was created
        $this->assertDatabaseHas('machineries', [
            'id' => $machinery->id,
            'puc_due_date' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function it_can_check_puc_due_in_3_days()
    {
        $machinery = Machinery::factory()->create([
            'name' => 'Test JCB',
            'machine_id' => 'MCH-002',
            'vehicle_number' => 'XYZ789',
            'status' => 'active',
            'puc_due_date' => now()->addDays(3)->toDateString(),
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        $this->assertDatabaseHas('machineries', [
            'id' => $machinery->id,
            'puc_due_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    /** @test */
    public function it_can_check_puc_overdue()
    {
        $machinery = Machinery::factory()->create([
            'name' => 'Test Bulldozer',
            'machine_id' => 'MCH-003',
            'vehicle_number' => 'OLD456',
            'status' => 'active',
            'puc_due_date' => now()->subDay()->toDateString(),
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        $this->assertDatabaseHas('machineries', [
            'id' => $machinery->id,
            'puc_due_date' => now()->subDay()->toDateString(),
        ]);
    }

    /** @test */
    public function it_can_check_service_due_today()
    {
        $machinery = Machinery::factory()->create([
            'name' => 'Test Crane',
            'machine_id' => 'MCH-004',
            'vehicle_number' => 'CRN001',
            'status' => 'active',
            'maintenance_schedule' => now()->toDateString(),
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        $this->assertDatabaseHas('machineries', [
            'id' => $machinery->id,
            'maintenance_schedule' => now()->toDateString(),
        ]);
    }

    /** @test */
    public function it_ignores_inactive_machinery()
    {
        $machinery = Machinery::factory()->create([
            'name' => 'Inactive Machine',
            'machine_id' => 'MCH-005',
            'status' => 'inactive',
            'puc_due_date' => now()->toDateString(),
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        // Active filter should exclude this
        $activeMachinery = Machinery::where('status', 'active')
            ->where('puc_due_date', now()->toDateString())
            ->get();

        $this->assertCount(0, $activeMachinery);
    }

    /** @test */
    public function it_ignores_machinery_without_due_dates()
    {
        $machinery = Machinery::factory()->create([
            'name' => 'No Due Date Machine',
            'machine_id' => 'MCH-006',
            'status' => 'active',
            'puc_due_date' => null,
            'maintenance_schedule' => null,
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        // Should not be included in due date queries
        $machineryWithPuc = Machinery::where('status', 'active')
            ->whereNotNull('puc_due_date')
            ->get();

        $this->assertCount(0, $machineryWithPuc);
    }

    /** @test */
    public function it_can_get_due_condition_today()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $today = now()->toDateString();
        $condition = $this->invokePrivateMethod($service, 'getDueCondition', [$today]);

        $this->assertEquals(MachineryNotificationService::DUE_TODAY, $condition);
    }

    /** @test */
    public function it_can_get_due_condition_in_3_days()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $threeDaysLater = now()->addDays(3)->toDateString();
        $condition = $this->invokePrivateMethod($service, 'getDueCondition', [$threeDaysLater]);

        $this->assertEquals(MachineryNotificationService::DUE_IN_3_DAYS, $condition);
    }

    /** @test */
    public function it_can_get_due_condition_overdue()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $yesterday = now()->subDay()->toDateString();
        $condition = $this->invokePrivateMethod($service, 'getDueCondition', [$yesterday]);

        $this->assertEquals(MachineryNotificationService::OVERDUE, $condition);
    }

    /** @test */
    public function it_returns_null_for_future_dates()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $futureDate = now()->addWeek()->toDateString();
        $condition = $this->invokePrivateMethod($service, 'getDueCondition', [$futureDate]);

        $this->assertNull($condition);
    }

    /** @test */
    public function it_returns_null_for_empty_dates()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $condition = $this->invokePrivateMethod($service, 'getDueCondition', [null]);

        $this->assertNull($condition);
    }

    /** @test */
    public function it_can_build_puc_message_for_today()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $machinery = new Machinery([
            'machine_id' => 'MCH-001',
            'vehicle_number' => 'ABC123',
            'puc_due_date' => now()->toDateString(),
        ]);

        $message = $this->invokePrivateMethod($service, 'buildPucMessage', [
            $machinery,
            MachineryNotificationService::DUE_TODAY
        ]);

        $this->assertStringContainsString('PUC for Machinery MCH-001 (ABC123) is due today', $message);
    }

    /** @test */
    public function it_can_build_puc_message_for_overdue()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $machinery = new Machinery([
            'machine_id' => 'MCH-002',
            'vehicle_number' => 'XYZ789',
            'puc_due_date' => now()->subDays(5)->toDateString(),
        ]);

        $message = $this->invokePrivateMethod($service, 'buildPucMessage', [
            $machinery,
            MachineryNotificationService::OVERDUE
        ]);

        $this->assertStringContainsString('PUC for Machinery MCH-002 (XYZ789) is overdue', $message);
    }

    /** @test */
    public function it_can_build_service_message()
    {
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        $machinery = new Machinery([
            'machine_id' => 'MCH-003',
            'vehicle_number' => 'JCB001',
            'maintenance_schedule' => now()->addDays(3)->toDateString(),
        ]);

        $message = $this->invokePrivateMethod($service, 'buildServiceMessage', [
            $machinery,
            MachineryNotificationService::DUE_IN_3_DAYS
        ]);

        $this->assertStringContainsString('Service for Machinery MCH-003 (JCB001) is due in 3 days', $message);
    }

    /** @test */
    public function it_prevents_duplicate_notifications()
    {
        $machinery = Machinery::factory()->create([
            'machine_id' => 'MCH-010',
            'status' => 'active',
            'puc_due_date' => now()->toDateString(),
            'site_id' => 1,
            'workspace_id' => 1,
        ]);

        // Create existing notification
        ChNotification::create([
            'type' => MachineryNotificationService::NOTIFICATION_TYPE_PUC,
            'title' => 'Test Notification',
            'message' => 'Test message',
            'related_id' => $machinery->id,
            'related_type' => 'Machinery',
            'message_arr' => [
                'due_condition' => MachineryNotificationService::DUE_TODAY,
            ],
            'created_at' => now(),
        ]);

        // Create mock service to test skip logic
        $service = new MachineryNotificationService(
            Mockery::mock(NotificationService::class)
        );

        // Should skip - notification exists for today
        $shouldSkip = $this->invokePrivateMethod($service, 'shouldSkipNotification', [
            $machinery->id,
            MachineryNotificationService::NOTIFICATION_TYPE_PUC,
            MachineryNotificationService::DUE_TODAY
        ]);

        $this->assertTrue($shouldSkip);
    }

    /** @test */
    public function job_can_be_instantiated()
    {
        $job = new CheckMachineryDueDateNotification();

        $this->assertInstanceOf(CheckMachineryDueDateNotification::class, $job);
        $this->assertEquals('notifications', $job->queue);
    }

    /**
     * Helper method to invoke private/protected methods
     */
    protected function invokePrivateMethod($object, string $method, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($object, ...$args);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}