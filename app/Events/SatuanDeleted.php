<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SatuanDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $satuanId;

    public function __construct($satuanId)
    {
        $this->satuanId = $satuanId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('satuans'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'satuan.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->satuanId,
        ];
    }
}