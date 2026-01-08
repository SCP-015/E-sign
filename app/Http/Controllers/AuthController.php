<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Services\GoogleMobileLoginService;
use App\Http\Requests\Auth\GoogleMobileLoginRequest;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    protected $googleMobileLoginService;

    public function __construct(GoogleMobileLoginService $googleMobileLoginService)
    {
        $this->googleMobileLoginService = $googleMobileLoginService;
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            $user = User::create([
                'name' => $request->name ?? 'Test User',
                'email' => $request->email,
                'password' => Hash::make($request->password ?? 'password'),
                'kyc_status' => 'unverified',
            ]);
        } else {
            if ($request->password && !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }

        $token = $user->createToken('auth_token')->accessToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => new UserResource($user)
        ]);
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
            return response()->json(['error' => 'Google Login Failed: ' . $e->getMessage()], 500);
        }
    }

    // --- MOBILE GOOGLE LOGIN ---

    public function googleMobileLogin(GoogleMobileLoginRequest $request)
    {
        try {
            $result = $this->googleMobileLoginService->handleMobileLogin(
                $request->id_token,
                $request->access_token,
                $request->code
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    public function googleMobileLoginCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        try {
            $result = $this->googleMobileLoginService->handleMobileLogin(
                null,
                null,
                $request->code
            );
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 500);
        }
    }

    // --- LOGOUT ENDPOINT ---

    public function logout(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        try {
            // Revoke all tokens for this user
            $user->tokens()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Logout successful'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
