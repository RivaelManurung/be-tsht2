<?php

namespace App\Events;

use App\Models\BarangCategory;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BarangCategoryDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $barangCategoryId;

    public function __construct($barangCategoryId)
    {
        $this->barangCategoryId = $barangCategoryId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('barang-categories'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'barang-category.deleted';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->barangCategoryId,
        ];
    }
}