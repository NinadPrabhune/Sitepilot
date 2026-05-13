<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendTestNotification extends Command
{
    protected $signature = 'notification:test {--project-id= : Project ID to send notification to} {--user-id= : User ID to send notification to (default: 1)} {--type=TEST : Notification type} {--title= : Notification title} {--message= : Notification message}';
    protected $description = 'Send a test notification to verify Pusher and Firebase are working';

    public function handle()
    {
        $projectId = $this->option('project-id');
        $userId = $this->option('user-id') ?? 1;
        $type = $this->option('type');
        $title = $this->option('title') ?? 'Test Notification 🚀';
        $message = $this->option('message') ?? 'This is a real-time + FCM test from CLI';

        // If no project ID provided, get the first available project
        if (!$projectId) {
            $projectId = DB::table('projects')->value('id');
            if (!$projectId) {
                $this->error('No projects found in database');
                return 1;
            }
            $this->info("Using project ID: {$projectId}");
        }

        // Verify user exists
        $userExists = DB::table('users')->where('id', $userId)->exists();
        if (!$userExists) {
            $this->error("User ID {$userId} not found");
            return 1;
        }

        $this->info("Sending test notification...");
        $this->info("Project ID: {$projectId}");
        $this->info("User ID: {$userId}");
        $this->info("Type: {$type}");

        try {
            $notificationService = app(NotificationService::class);

            $notification = $notificationService->create(
                type: $type,
                title: $title,
                message: $message,
                userIds: [$userId],
                projectId: $projectId,
                iconType: 'info',
            );

            $this->info("✅ Notification sent successfully!");
            $this->info("Notification ID: {$notification->id}");
            $this->newLine();
            $this->info("Check:");
            $this->info("• Browser console for 'Real-time notification received'");
            $this->info("• Mobile device for push notification");
            $this->info("• Logs: tail -f storage/logs/laravel.log");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Error: " . $e->getMessage());
            return 1;
        }
    }
}
