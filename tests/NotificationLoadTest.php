<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notification Load Test Script
 * 
 * Usage: php artisan tinker
 * Then: include 'tests/NotificationLoadTest.php';
 * Then: (new Tests\NotificationLoadTest())->run();
 */

class NotificationLoadTest
{
    private $notificationService;
    private $testProjectId;
    private $testUserIds;

    public function __construct()
    {
        $this->notificationService = app(NotificationService::class);
        
        // Use a real project ID from your database
        $this->testProjectId = DB::table('projects')->value('id');
        
        // Get real user IDs from the project
        $this->testUserIds = DB::table('user_projects')
            ->where('project_id', $this->testProjectId)
            ->pluck('user_id')
            ->take(5) // Limit to 5 users for testing
            ->toArray();
    }

    public function run()
    {
        $this->log('=== Starting Notification Load Test ===');
        $this->log('Project ID: ' . $this->testProjectId);
        $this->log('Test Users: ' . count($this->testUserIds));

        if (empty($this->testUserIds)) {
            $this->log('ERROR: No test users found');
            return;
        }

        $startTime = microtime(true);
        $notificationCount = 50;
        $successCount = 0;
        $failureCount = 0;

        $this->log("Sending {$notificationCount} notifications...");

        for ($i = 0; $i < $notificationCount; $i++) {
            try {
                $this->notificationService->create(
                    type: 'load_test',
                    title: "Load Test Notification #{$i}",
                    message: "This is a load test notification #{$i}",
                    userIds: $this->testUserIds,
                    projectId: $this->testProjectId,
                    iconType: 'info',
                );
                $successCount++;
                
                // Add small delay to avoid overwhelming the system
                usleep(100000); // 0.1 second
                
            } catch (\Exception $e) {
                $failureCount++;
                $this->log("ERROR on notification #{$i}: " . $e->getMessage());
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $throughput = round($notificationCount / $duration, 2);

        $this->log('=== Load Test Results ===');
        $this->log("Total notifications: {$notificationCount}");
        $this->log("Successful: {$successCount}");
        $this->log("Failed: {$failureCount}");
        $this->log("Duration: {$duration} seconds");
        $this->log("Throughput: {$throughput} notifications/second");
        $this->log('=== Load Test Complete ===');

        // Check for duplicate notifications
        $this->checkForDuplicates();
    }

    private function checkForDuplicates()
    {
        $this->log('Checking for duplicate notifications...');
        
        $duplicates = DB::table('ch_notification_users')
            ->select('notification_id', 'user_id', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subMinutes(5))
            ->groupBy('notification_id', 'user_id')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->log('WARNING: Found ' . $duplicates->count() . ' duplicate notification entries');
        } else {
            $this->log('No duplicate notifications found');
        }
    }

    private function log($message)
    {
        echo "[" . date('Y-m-d H:i:s') . "] {$message}\n";
        Log::info($message);
    }
}
