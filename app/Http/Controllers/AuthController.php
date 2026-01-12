<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\GoogleMobileLoginCodeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Services\AuthService;
use App\Http\Requests\Auth\GoogleMobileLoginRequest;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request)
    {
        return ApiResponse::fromService($this->authService->login($request->validated()));
    }

    // --- WEB GOOGLE LOGIN ---

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName(),
                    'password' => Hash::make(Str::random(16)),
                    'kyc_status' => 'unverified'
                ]
            );

            // Always update name and avatar from Google profile on every login
            $user->update([
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
            ]);

            $token = $user->createToken('auth_token')->accessToken;

            return redirect(URL::to('/?token=' . urlencode($token)));

        } catch (\Exception $e) {
            return ApiResponse::error('Google Login Failed: ' . $e->getMessage(), 500);
        }
    }

    // --- MOBILE GOOGLE LOGIN ---

    public function googleMobileLogin(GoogleMobileLoginRequest $request)
    {
        try {
            return ApiResponse::fromService(
                $this->authService->googleMobileLogin($request->id_token, $request->access_token, $request->code)
            );
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function googleMobileLoginCode(GoogleMobileLoginCodeRequest $request)
    {
        try {
            return ApiResponse::fromService($this->authService->googleMobileLogin(null, null, $request->code));
        } catch (\Exception $e) {
            return ApiResponse::error($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // --- LOGOUT ENDPOINT ---

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        return ApiResponse::fromService($this->authService->logout((int) $user->id));
    }
}
