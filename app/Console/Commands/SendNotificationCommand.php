<?php

namespace App\Console\Commands;

use App\Events\StockMinimumReached;
use App\Models\Notifikasi;
use Illuminate\Console\Command;

class SendNotificationCommand extends Command
{
    protected $signature = 'app:send-notification-command {text} {type}';
    protected $description = 'Send stock notification';

    public function handle()
    {
        $icon = match($this->argument("type")) {
            'user' => "fa fa-user",
            'question' => "fa fa-question-circle",
            default => "fa fa-info-circle"
        };

        $title = 'Stock Update';
        $message = $this->argument("text");

        $notification = Notifikasi::create([
            "title" => $title,
            "message" => $message,
            "icon" => $icon,
        ]);

        broadcast(new StockMinimumReached($title, $message, $notification->barang_id, $notification->gudang_id));
    }
}
