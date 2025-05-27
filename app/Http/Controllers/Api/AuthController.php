<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(Request $request): JsonResponse
{
    $credentials = $request->validate([
        'name' => 'required|string',
        'password' => 'required|string',
    ]);

    $result = $this->authService->login($credentials);

    return response()->json($result, $result['response_code']);
}


    public function logout(Request $request): JsonResponse
    {
        $user = Auth::user();
        $result = $this->authService->logout($user);

        return response()->json($result, $result['response_code']);
    }

    public function userInfo(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Ambil permissions dari AuthService
        $permissions = $this->authService->getUserPermissions($user);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
                'address' => $user->address,
                'created_at' => $user->created_at,
                'roles' => $user->roles ? $user->roles->pluck('name') : [],
                'permissions' => $permissions,
            ],
        ], 200);
    }


}
