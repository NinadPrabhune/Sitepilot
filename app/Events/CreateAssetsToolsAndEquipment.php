<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\AssetsToolsAndEquipment;
use Illuminate\Http\Request;


class CreateAssetsToolsAndEquipment
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $request;
    public $tool;

    public function __construct(Request $request, AssetsToolsAndEquipment $tool)
    {
        $this->request = $request;
        $this->tool = $tool;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
