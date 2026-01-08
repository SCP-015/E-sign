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
     * Handle Google Mobile Login with ID Token, Access Token, or Auth Code
     */
    public function handleMobileLogin(?string $idToken = null, ?string $accessToken = null, ?string $code = null): array
    {
        try {
            $payload = null;

            // STRATEGY 1: Use ID Token if available
            if ($idToken) {
                $payload = $this->verifyIdToken($idToken);
                if (!$payload) {
                    Log::warning('ID Token verification failed, will try other methods if available');
                }
            }
            
            // STRATEGY 2: Use Access Token if ID Token failed/missing AND access_token exists
            if (!$payload && $accessToken) {
                $payload = $this->verifyAccessToken($accessToken);
                if (!$payload) {
                    throw new \Exception('Google access token verification failed', 401);
                }
            }
            
            // STRATEGY 3: Exchange Auth Code if both tokens failed/missing AND code exists
            if (!$payload && $code) {
                $payload = $this->exchangeAuthCode($code);
                if (!$payload) {
                    throw new \Exception('Failed to exchange authorization code', 400);
                }
            }

            if (!$payload) {
                throw new \Exception('Invalid Google Credentials (id_token, access_token, or code required)', 400);
            }

            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? null;
            $emailVerified = $payload['email_verified'] ?? false;
            $picture = $payload['picture'] ?? null;
            
            if (!$email || !$emailVerified) {
                throw new \Exception('Google user verification failed', 401);
            }

            // Find or Create User
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name ?? $email,
                    'password' => Hash::make(Str::random(16)),
                    'kyc_status' => 'unverified',
                ]
            );

            // Update user avatar if provided
            if ($picture && !$user->avatar) {
                $user->avatar = $picture;
                $user->save();
            }

            // Generate Token (Passport)
            $token = $user->createToken('mobile_auth_token')->accessToken;

            // Fetch Certificate info if available
            $cert = $user->certificate;
            $certificateData = null;

            if ($cert) {
                $certificateData = [
                    'id' => $cert->id,
                    'certificate_number' => $cert->certificate_number,
                    'status' => $cert->status,
                    'issued_at' => $cert->issued_at,
                    'expires_at' => $cert->expires_at,
                    'certificate_type' => $cert->certificate_type,
                ];
            }

            return [
                'status' => 'success',
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $picture,
                    'email_verified_at' => $user->email_verified_at,
                    'kyc_status' => $user->kyc_status,
                    'created_at' => $user->created_at,
                ],
                'certificate' => $certificateData,
                'has_certificate' => $cert && $cert->status === 'active',
                'documents_count' => $user->documents()->count(),
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
            
            if (!$payload) {
                Log::warning('ID Token verification returned empty payload');
                return false;
            }
            
            return $payload;
        } catch (\Exception $e) {
            Log::error('Error verifying Google ID Token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify Access Token using Google's userinfo API
     * IMPORTANT: Do NOT use tokeninfo endpoint or try to decode access_token as JWT
     */
    private function verifyAccessToken(string $accessToken)
    {
        try {
            // Use Google's userinfo endpoint to validate access_token
            // This is the CORRECT way to validate OAuth2 access tokens
            $client = new \Google\Client();
            $client->setAccessToken($accessToken);
            
            // Verify the token is valid by making an API call
            $oauth2 = new \Google\Service\Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            
            if (!$userInfo || !$userInfo->email) {
                Log::error('Access token validation failed: No user info returned');
                return false;
            }
            
            return [
                'email' => $userInfo->email,
                'name' => $userInfo->name,
                'email_verified' => $userInfo->verifiedEmail,
                'sub' => $userInfo->id,
                'picture' => $userInfo->picture
            ];
            
        } catch (\Exception $e) {
            Log::error('Error verifying Google Access Token: ' . $e->getMessage());
            return false;
        }
    }

    private function exchangeAuthCode(string $code)
    {
        try {
            // NOTE: When exchanging code from Mobile (Android/iOS), 
            // the Client ID used for exchange MUST be the WEB Client ID, 
            // even if the code was generated by the Mobile Client ID.
            // Standard flow: Mobile App uses "Web Client ID" for serverAuthCode request.
            // So backend must use WEB Client Secret.
            
            $client = new \Google\Client();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri('postmessage'); // Standard for mobile auth code flow
            
            // Exchange the auth code for tokens
            $token = $client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                 Log::error('Google Code Exchange Error: ' . json_encode($token));
                 throw new \Exception('Failed to exchange authorization code: ' . $token['error']);
            }

            // The response contains 'id_token' which provides user info
            if (isset($token['id_token'])) {
                $payload = $client->verifyIdToken($token['id_token']);
                if ($payload) {
                    return $payload;
                }
            }
            
            // Fallback: If no id_token in response, get user info manually using access_token
            if (isset($token['access_token'])) {
                $client->setAccessToken($token);
                $oauth2 = new \Google\Service\Oauth2($client);
                $userInfo = $oauth2->userinfo->get();
                
                return [
                    'email' => $userInfo->email,
                    'name' => $userInfo->name,
                    'email_verified' => $userInfo->verifiedEmail,
                    'sub' => $userInfo->id,
                    'picture' => $userInfo->picture
                ];
            }
            
            throw new \Exception('No valid token received from Google');

        } catch (\Exception $e) {
             Log::error('Error exchanging Google Code: ' . $e->getMessage());
             throw $e;
        }
    }
}
