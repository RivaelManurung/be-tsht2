<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\WebController;
use App\Http\Controllers\BarangCategoryController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\BarangStatusController;
use App\Http\Controllers\GudangController;
use App\Http\Controllers\JenisBarangController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QRCodeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SatuanController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

// prefix untuk auth
Route::prefix('auth')->group(function () {
    Route::controller(AuthController::class)->group(function () {
        Route::post('login', 'login')->name('auth.login');
    });
    // untuk logout dan cek user info
    Route::middleware('auth:api')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('refresh-permission', [AuthController::class, 'refreshPermissions']);

        Route::get('update-profile', [AuthController::class, 'updateprofil']);
        Route::get('user', [AuthController::class, 'userInfo'])->name('auth.user');
    });
});

// untuk role super admin
Route::middleware(['auth:api'])->group(function () {
    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Hanya Superadmin bisa akses']);
    });

    Route::get('user/operators', [UserController::class, 'getOperators']);
    Route::put('/users/admin-update/{id}', [UserController::class, 'updateUserByAdmin']);
    Route::apiResource('users', UserController::class);
    Route::post('/users/change-password', [UserController::class, 'changePassword']);
    Route::delete('/users/{id}/avatar', [UserController::class, 'deleteAvatar']);

    Route::apiResource('roles', RoleController::class);

    Route::apiResource('gudangs', GudangController::class);

    Route::apiResource('satuans', SatuanController::class);


    Route::apiResource('barang-categories', BarangCategoryController::class);

    Route::apiResource('transaction-types', TransactionTypeController::class);
    Route::apiResource('webs', WebController::class);
    //route laporan
    Route::get('laporantransaksi', [LaporanController::class, 'laporantransaksi']);
    Route::get('/laporan-transaksi/export-pdf', [LaporanController::class, 'generateTransaksiReportPdf'])->name('transactions.exportPdf');
    Route::get('/laporan-transaksi/export-pdf/{typeId}', [LaporanController::class, 'generateTransaksiTypeReportPdf'])->middleware('auth:api');
    Route::get('/laporan-transaksi/export-excel/{id}', [LaporanController::class, 'generateTransaksiTypeReportexcel']);
    Route::get('/laporan-transaksi/export-excel', [LaporanController::class, 'generateAllTransaksiexcel']);

    Route::get('laporanstok', [LaporanController::class, 'laporanstok']);
    Route::get('laporan-stok/pdf', [LaporanController::class, 'exportStokPdf']);
    Route::get('laporan-stok/excel', [LaporanController::class, 'exportStokExcel']);

    // routes/api.php
    Route::get('transactions/check-barcode/{kode}', [TransactionController::class, 'checkBarcode']);
    Route::apiResource('transactions', TransactionController::class);
    Route::post('transactions/update-barang-details', [TransactionController::class, 'updateBarangDetails']); // New endpoint for stock/name updates

    Route::apiResource('jenis-barangs', JenisBarangController::class);
    Route::patch('jenis-barang/{id}/restore', [JenisBarangController::class, 'restore']);
    Route::delete('jenis-barang/{id}/force-delete', [JenisBarangController::class, 'forceDelete']);

    Route::post('/otp/send', [LaporanController::class, 'send']);
    Route::post('/otp/verify', [LaporanController::class, 'verify']);

    //barang
    Route::apiResource('barangs', BarangController::class);

    Route::get('/barang/qrcode/save/{id}', [QRCodeController::class, 'generateQRCodeImage']);
    Route::get('/generate-qrcodes', [QRCodeController::class, 'generateAllQRCodesImage']);

    Route::get('/barangs/export-pdf/{id}', [QRCodeController::class, 'generateQRCodePDF']);
    Route::get('/export-pdf', [QRCodeController::class, 'generateAllQRCodesPDF']);
});
Route::middleware(['auth:api', 'role_or_permission:superadmin|manage_permissions'])->group(function () {
    Route::post('/toggle-permission', [PermissionController::class, 'togglePermission']);
    Route::get('/permission', [PermissionController::class, 'index']);
});

Route::post('/email/resend', function (Request $request) {
    $user = $request->user();

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email sudah diverifikasi.']);
    }

    $pendingEmail = Cache::get('pending_email_' . $user->id);
    if (!$pendingEmail) {
        return response()->json(['message' => 'Belum ada email baru yang didaftarkan.'], 400);
    }

    $cacheKey = 'email_verification_timestamp_' . $user->id;
    $lastSent = Cache::get($cacheKey);

    $cooldown = 60;
    if ($lastSent) {
        $elapsed = now()->diffInSeconds($lastSent);
        if ($elapsed < $cooldown) {
            return response()->json([
                'message' => 'Coba lagi setelah 1 menit.',
                'countdown' => $cooldown - $elapsed
            ], 400);
        }
    }

    Cache::put($cacheKey, now(), 61);

    $user->forceFill(['email' => $pendingEmail])
        ->sendEmailVerificationNotification();

    return response()->json(['message' => 'Link verifikasi telah dikirim ulang ke email baru.']);
})->middleware(['auth:api']);


// Link verifikasi yang diklik di email

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = \App\Models\User::findOrFail($id);
    $pendingEmail = Cache::get('pending_email_' . $user->id);

    if (!$pendingEmail) {
        return response()->json(['message' => 'Tidak ada email baru yang menunggu verifikasi.'], 400);
    }

    // Pastikan hash cocok dengan email baru
    if (!hash_equals((string) $hash, sha1($pendingEmail))) {
        return response()->json(['message' => 'Link verifikasi tidak valid.'], 400);
    }

    // Tandai email baru sebagai terverifikasi dan simpan
    $user->email = $pendingEmail;
    $user->email_verified_at = now();
    $user->save();

    // Hapus dari cache
    Cache::forget('pending_email_' . $user->id);

    return response()->json(['message' => 'Email berhasil diperbarui dan diverifikasi.']);
})->middleware(['signed'])->name('verification.verify');

Route::put('/user/update-email', [UserController::class, 'updateEmail'])->middleware(['auth:api']);



//memastikan cek role login
Route::middleware(['auth:api'])->get('/check-roles', [UserController::class, 'checkRoles']);

Route::get('/notifikasis', [NotifikasiController::class, 'index']);
Route::post('/notifikasis/{id}/read', [NotifikasiController::class, 'markAsRead']);
