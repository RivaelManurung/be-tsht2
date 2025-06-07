<?php

namespace App\Events;

use App\Models\Satuan;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SatuanCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $satuan;

    public function __construct(Satuan $satuan)
    {
        $this->satuan = $satuan;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('satuans'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'satuan.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->satuan->id,
            'name' => $this->satuan->name,
            'slug' => $this->satuan->slug,
            'description' => $this->satuan->description,
            'user_id' => $this->satuan->user_id,
            'created_at' => $this->satuan->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->satuan->updated_at->format('Y-m-d H:i:s'),
            'deleted_at' => $this->satuan->deleted_at ? $this->satuan->deleted_at->format('Y-m-d H:i:s') : null,
        ];
    }
}