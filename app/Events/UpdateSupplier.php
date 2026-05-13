<?php

namespace App\Events;

use App\Models\Supplier;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdateSupplier
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

  
    
    
    public $request;
    public $supplier;

    public function __construct($request, Supplier $supplier)
    {
        $this->request = $request;
        $this->supplier = $supplier;
    }
}
