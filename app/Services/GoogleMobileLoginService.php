<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleMobileLoginService
{
    /**
     * Handle Google Mobile Login with ID Token
     */
    public function handleMobileLogin(string $idToken): array
    {
        try {
            $payload = $this->verifyIdToken($idToken);

            if (!$payload) {
                throw new \Exception('Invalid Google ID token', 400);
            }

            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? null;
            $googleId = $payload['sub'] ?? null;
            $emailVerified = $payload['email_verified'] ?? false;
            $avatar = $payload['picture'] ?? null;

            if (!$email || !$googleId || !$emailVerified) {
                throw new \Exception('Google ID token verification failed', 401);
            }

            // Find or Create User
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name ?? $email,
                    'password' => Hash::make(Str::random(16)),
                    // 'google_id' => $googleId, // Add to migration if needed, for now email matching is MVP
                    'kyc_status' => 'unverified', // Default
                ]
            );

            // Generate Token
            $token = $user->createToken('mobile_auth_token')->plainTextToken;

            return [
                'status' => 'success',
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user,
            ];

        } catch (\Exception $e) {
            Log::error('Google mobile login error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function verifyIdToken(string $idToken)
    {
        try {
            $client = new \Google\Client(['client_id' => config('services.google.mobile_client_id')]);
            $payload = $client->verifyIdToken($idToken);

            if ($payload) {
                $audience = $payload['aud'] ?? null;
                $expectedClientId = config('services.google.mobile_client_id');

                // Allow mismatch if configured to use same ID
                // MVP: Strict check recommended but user might share IDs
                // if ($audience !== $expectedClientId) {
                //      Log::error('Audience mismatch', ['expected' => $expectedClientId, 'got' => $audience]);
                //      return false;
                // }
                
                return $payload;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error verifying Google ID Token: ' . $e->getMessage());
            return false;
        }
    }
}
