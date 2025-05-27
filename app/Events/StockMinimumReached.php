<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMinimumReached implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $title;
    public $message;
    public $barang_id;
    public $gudang_id;


    /**
     * Create a new event instance.
     */

     public function __construct($title, $message, $barang_id, $gudang_id)
     {
         $this->title = $title;
         $this->message = $message;
         $this->barang_id = $barang_id;
         $this->gudang_id = $gudang_id;
     }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */

     public function broadcastOn()
     {
         return new Channel('stock-channel');
     }

     public function broadcastAs()
     {
         return 'stock.minimum';
     }
}
