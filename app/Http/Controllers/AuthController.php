<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Services\GoogleMobileLoginService;

class AuthController extends Controller
{
    protected $googleMobileLoginService;

    public function __construct(GoogleMobileLoginService $googleMobileLoginService)
    {
        $this->googleMobileLoginService = $googleMobileLoginService;
    }
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string',
            'password' => 'nullable|string', // Optional, if just "entering" for MVP demo
        ]);

        $user = User::where('email', $request->email)->first();

        // For MVP Speed: "Auto-Register" if user doesn't exist, but purely on Email.
        // In Prod: separate Register and Login.
        // User asked for "Manual Login... test user".
        
        if (!$user) {
            // Register Flow
            $user = User::create([
                'name' => $request->name ?? 'Test User',
                'email' => $request->email,
                'password' => Hash::make($request->password ?? 'password'), // Default password if none
                'kyc_status' => 'unverified',
                // Removed google_id
            ]);
        } else {
            // Login Flow - Verify password if provided
            if ($request->password && !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
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

            $token = $user->createToken('auth_token')->plainTextToken;

            // In a real SPA, we would redirect to frontend with token in query param or cookie
            // For this MVP, we return JSON or redirect to dashboard with token in URL (fragile but simple)
            // Or use postMessage. 
            // Better: Redirect to frontend /callback?token=...
            
            // Assuming frontend is at root /
            return redirect('/?token=' . $token);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Google Login Failed: ' . $e->getMessage()], 500);
        }
    }

    // --- MOBILE GOOGLE LOGIN ---

    public function googleMobileLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        try {
            $result = $this->googleMobileLoginService->handleMobileLogin($request->id_token);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], $e->getCode() ?: 500);
        }
    }
}
