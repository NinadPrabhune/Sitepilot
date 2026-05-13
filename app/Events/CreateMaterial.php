<?php

namespace App\Events;

use App\Models\Material;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class CreateMaterial
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $request;
    public $material;

    public function __construct($request, Material $material)
    {
        $this->request = $request;
        $this->material = $material;
    }
}
