<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class UserController extends Controller implements HasMiddleware
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public static function middleware(): array
    {
        return [
            'auth:api',
            new Middleware('permission:view_user', only: ['index', 'show']),
            new Middleware('permission:create_user', only: ['store']),
            new Middleware('permission:update_user', only: ['update', 'changePassword']),
            new Middleware('permission:delete_user', only: ['destroy', 'deleteAvatar']),
        ];
    }

    public function index()
    {
        return UserResource::collection($this->userService->getAll());
    }

    public function getOperators()
    {
        $operators = $this->userService->getOperators();
        return UserResource::collection($operators);
    }

    public function show($id)
    {
        try {
            $user = $this->userService->getById($id);
            if (!$user) {
                return response()->json(['message' => 'User tidak ditemukan'], 404);
            }

            return new UserResource($user);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = $this->userService->create($request->all());

            return response()->json([
                'message' => 'User berhasil dibuat',
                'data' => new UserResource($user)
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $allowedFields = $request->only(['name', 'phone_number', 'avatar']);
            $user = $this->userService->update($id, $allowedFields);

            return response()->json([
                'message' => 'Profil berhasil diperbarui',
                'data' => new UserResource($user)
            ]);
        } catch (\Exception $e) {
            Log::error("Error updating user: " . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteAvatar($id)
    {
        try {
            $user = $this->userService->deleteAvatar($id);

            return response()->json([
                'message' => 'Avatar berhasil dihapus',
                'data' => new UserResource($user)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 400);
        }
    }


    public function destroy($id)
    {
        try {
            $this->userService->delete($id);

            return response()->json(['message' => 'Pengguna berhasil dihapus'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function updateUserByAdmin(Request $request, $id)
{
    try {
        $user = $this->userService->updateUserByAdmin($id, $request->all());

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui oleh admin',
            'data' => new UserResource($user)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Gagal memperbarui data pengguna',
            'error' => $e->getMessage()
        ], 422);
    }
}public function updateEmail(Request $request)
{
    $request->validate([
        'email' => 'required|email|email:rfc,dns',
    ]);

    $user = Auth::user();

    if ($request->email === $user->email) {
        return response()->json(['message' => 'Email yang dimasukkan sama dengan email saat ini.'], 400);
    }

    if (\App\Models\User::where('email', $request->email)->exists()) {
        return response()->json(['message' => 'Email sudah digunakan oleh pengguna lain.'], 400);
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

    // Simpan timestamp dan email baru
    Cache::put($cacheKey, now(), 61);
    Cache::put('pending_email_' . $user->id, $request->email, now()->addHour());

    // Buat URL verifikasi manual
    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        Carbon::now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($request->email)]
    );

    // Kirim notifikasi (opsional, jika tetap ingin kirim email)
    $user->forceFill(['email' => $request->email])
         ->sendEmailVerificationNotification();

    return response()->json([
        'message' => 'Link verifikasi telah dikirim ke email baru. Harap cek inbox Anda.',
        'verification_url' => $verificationUrl
    ]);
}
}
