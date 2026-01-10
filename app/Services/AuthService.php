<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private readonly GoogleMobileLoginService $googleMobileLoginService)
    {
    }

    public function login(array $validated): array
    {
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            $user = User::create([
                'name' => $validated['name'] ?? 'Test User',
                'email' => $validated['email'],
                'password' => Hash::make($validated['password'] ?? 'password'),
                'kyc_status' => 'unverified',
            ]);
        } else {
            if (!Hash::check($validated['password'] ?? '', $user->password)) {
                return [
                    'status' => 'error',
                    'code' => 401,
                    'message' => 'Invalid credentials',
                    'data' => null,
                ];
            }
        }

        $token = $user->createToken('auth_token')->accessToken;

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => (new UserResource($user))->resolve(),
            ],
        ];
    }

    public function logout(int $userId): array
    {
        $user = User::findOrFail($userId);
        $user->tokens()->delete();

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Logout successful',
            'data' => null,
        ];
    }

    public function googleMobileLogin(?string $idToken, ?string $accessToken, ?string $code): array
    {
        $result = $this->googleMobileLoginService->handleMobileLogin($idToken, $accessToken, $code);

        $data = $result;
        $status = $data['status'] ?? 'success';
        $message = $data['message'] ?? 'OK';
        unset($data['status'], $data['message'], $data['code']);

        return [
            'status' => $status,
            'code' => 200,
            'message' => $message,
            'data' => $data,
        ];
    }
}
