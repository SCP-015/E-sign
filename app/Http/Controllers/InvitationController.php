<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\DocumentSigner;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function validateInvitation(Request $request)
    {
        if ($request->filled('code')) {
            $validated = $request->validate([
                'code' => 'required|string',
            ]);

            $signer = DocumentSigner::with('document')
                ->where('invite_token', $validated['code'])
                ->first();
        } else {
            $validated = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
            ]);

            $signer = DocumentSigner::with('document')
                ->where('email', $validated['email'])
                ->where('invite_token', $validated['token'])
                ->first();
        }

        if (!$signer) {
            return ApiResponse::error('Invalid invitation', 404);
        }

        if ($signer->invite_accepted_at) {
            return ApiResponse::error('Invitation already accepted', 410);
        }

        if ($signer->invite_expires_at && $signer->invite_expires_at->isPast()) {
            return ApiResponse::error('Invitation expired', 410);
        }

        return ApiResponse::success([
            'valid' => true,
            'email' => $signer->email,
            'document_id' => $signer->document_id,
            'document_title' => $signer->document?->title,
            'signer_id' => $signer->id,
            'expires_at' => $signer->invite_expires_at?->toIso8601String(),
        ], 'OK', 200);
    }

    public function accept(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return ApiResponse::error('Unauthenticated', 401);
        }

        if ($request->filled('code')) {
            $validated = $request->validate([
                'code' => 'required|string',
            ]);

            $signer = DocumentSigner::with('document')
                ->where('invite_token', $validated['code'])
                ->first();

            if ($signer && strtolower($user->email) !== strtolower($signer->email)) {
                return ApiResponse::error('Invitation email does not match logged-in user', 403);
            }
        } else {
            $validated = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
            ]);

            if (strtolower($user->email) !== strtolower($validated['email'])) {
                return ApiResponse::error('Invitation email does not match logged-in user', 403);
            }

            $signer = DocumentSigner::with('document')
                ->where('email', $validated['email'])
                ->where('invite_token', $validated['token'])
                ->first();
        }

        if (!$signer) {
            return ApiResponse::error('Invalid invitation', 404);
        }

        if ($signer->invite_accepted_at) {
            return ApiResponse::error('Invitation already accepted', 410);
        }

        if ($signer->invite_expires_at && $signer->invite_expires_at->isPast()) {
            return ApiResponse::error('Invitation expired', 410);
        }

        $signer->update([
            'user_id' => $user->id,
            'email' => strtolower($user->email),
            'name' => $user->name,
            'invite_accepted_at' => now(),
            'invite_token' => null,
            'invite_expires_at' => null,
        ]);

        return ApiResponse::success([
            'status' => 'accepted',
            'document_id' => $signer->document_id,
            'signer_id' => $signer->id,
        ], 'OK', 200);
    }
}
