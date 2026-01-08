<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TokenController extends Controller
{
    public function getToken(Request $request)
    {
        $token = session('auth_token');

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'No token found in session'
            ], 401);
        }

        // Clear token from session after retrieving (one-time use)
        session()->forget('auth_token');

        return response()->json([
            'status' => 'success',
            'token' => $token
        ]);
    }
}
