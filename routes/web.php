<?php

use App\Events\StockMinimumReached;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\Frontend\AuthController;
use App\Http\Controllers\Frontend\BarangController as FrontendBarangController;
use App\Http\Controllers\Frontend\DashboardController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test-broadcast', function () {
    event(new StockMinimumReached(
        "Stok Minimum Test",
        "Ini adalah notifikasi uji coba realtime!",
        1,
        1
    ));

    return "Notifikasi realtime dikirim!";
});
