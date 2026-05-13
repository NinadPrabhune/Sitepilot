<?php

namespace App\Events;

use App\Models\ChNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ChNotification $notification;

    // Retry configuration
    public $tries = 3;
    public $backoff = [10, 30, 60]; // Exponential backoff: 10s, 30s, 60s

    public function __construct(ChNotification $notification)
    {
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('site.' . $this->notification->project_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    // Queue the broadcast for scalability
    public function broadcastQueue(): string
    {
        return 'notifications';
    }

    // Guard: Check if notifications are enabled before broadcasting
    public function broadcastWhen(): bool
    {
        return config('app.send_notification', true);
    }
}
