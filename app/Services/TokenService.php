<?php

namespace App\Services;

class TokenService
{
    public function getToken(): array
    {
        $token = session('auth_token');

        if (!$token) {
            return [
                'status' => 'error',
                'code' => 401,
                'message' => 'No token found in session',
                'data' => null,
            ];
        }

        session()->forget('auth_token');

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'token' => $token,
            ],
        ];
    }
}
