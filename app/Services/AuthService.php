<?php

namespace App\Services;

use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected $authRepository;

    public function __construct(AuthRepository $authRepository)
    {
        $this->authRepository = $authRepository;
    }

    public function login(array $credentials): array
    {
        // Find user by name instead of email
        $user = $this->authRepository->findUserByName($credentials['name']);

        if (!$user) {
            return [
                'response_code' => 401,
                'status' => 'error',
                'message' => 'Name not found',
            ];
        }

        if (!Hash::check($credentials['password'], $user->password)) {
            return [
                'response_code' => 401,
                'status' => 'error',
                'message' => 'Incorrect password',
            ];
        }

        $accessToken = $this->authRepository->createToken($user);
        $permissions = $this->authRepository->getUserPermissions($user);
        $roles = $this->authRepository->getUserRoles($user);

        return [
            'response_code' => 200,
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email, // You can still return email if needed
                    'phone_number' => $user->phone_number,
                    'avatar' => $user->avatar,
                    'role_id' => $user->role_id,
                ],
                'token' => $accessToken,
                'permissions' => $permissions,
                'roles' => $roles,
            ],
        ];
    }


    public function logout($user): array
    {
        try {
            if (!$user) {
                return [
                    'response_code' => 401,
                    'status' => 'error',
                    'message' => 'User not authenticated',
                ];
            }

            $this->authRepository->revokeTokens($user);

            return [
                'response_code' => 200,
                'status' => 'success',
                'message' => 'Logout successful',
            ];
        } catch (\Exception $e) {
            Log::error($e);
            return [
                'response_code' => 500,
                'status' => 'error',
                'message' => 'Failed to logout',
            ];
        }
    }
    public function getUserPermissions($user)
    {
        return $this->authRepository->getUserPermissions($user);
    }

    public function getUserRoles($user)
    {
        return $this->authRepository->getUserRoles($user);
    }
}
