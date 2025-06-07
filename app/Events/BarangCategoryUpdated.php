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

class BarangCategoryUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $barangCategory;

    public function __construct(BarangCategory $barangCategory)
    {
        $this->barangCategory = $barangCategory;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('barang-categories'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'barang-category.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->barangCategory->id,
            'name' => $this->barangCategory->name,
            'slug' => $this->barangCategory->slug,
            'created_at' => $this->barangCategory->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->barangCategory->updated_at->format('Y-m-d H:i:s'),
            'deleted_at' => $this->barangCategory->deleted_at ? $this->barangCategory->deleted_at->format('Y-m-d H:i:s') : null,
        ];
    }
}