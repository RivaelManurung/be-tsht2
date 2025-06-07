<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('stock-channel', function () {
    return true;  // Mengizinkan semua pengguna untuk mendengarkan channel ini
});

Broadcast::channel('barang-categories', function () {
    return true;  // Mengizinkan semua pengguna untuk mendengarkan channel ini
});

Broadcast::channel('satuans', function () {
    return true;  // Mengizinkan semua pengguna untuk mendengarkan channel ini
});


