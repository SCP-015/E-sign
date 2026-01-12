<?php

namespace App\Http\Controllers;

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
            return response()->json(['message' => 'Invalid invitation'], 404);
        }

        if ($signer->invite_accepted_at) {
            return response()->json(['message' => 'Invitation already accepted'], 410);
        }

        if ($signer->invite_expires_at && $signer->invite_expires_at->isPast()) {
            return response()->json(['message' => 'Invitation expired'], 410);
        }

        return response()->json([
            'valid' => true,
            'email' => $signer->email,
            'documentId' => $signer->document_id,
            'documentTitle' => $signer->document?->title,
            'signerId' => $signer->id,
            'expiresAt' => $signer->invite_expires_at?->toIso8601String(),
        ]);
    }

    public function accept(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($request->filled('code')) {
            $validated = $request->validate([
                'code' => 'required|string',
            ]);

            $signer = DocumentSigner::with('document')
                ->where('invite_token', $validated['code'])
                ->first();

            if ($signer && strtolower($user->email) !== strtolower($signer->email)) {
                return response()->json(['message' => 'Invitation email does not match logged-in user'], 403);
            }
        } else {
            $validated = $request->validate([
                'email' => 'required|email',
                'token' => 'required|string',
            ]);

            if (strtolower($user->email) !== strtolower($validated['email'])) {
                return response()->json(['message' => 'Invitation email does not match logged-in user'], 403);
            }

            $signer = DocumentSigner::with('document')
                ->where('email', $validated['email'])
                ->where('invite_token', $validated['token'])
                ->first();
        }

        if (!$signer) {
            return response()->json(['message' => 'Invalid invitation'], 404);
        }

        if ($signer->invite_accepted_at) {
            return response()->json(['message' => 'Invitation already accepted'], 410);
        }

        if ($signer->invite_expires_at && $signer->invite_expires_at->isPast()) {
            return response()->json(['message' => 'Invitation expired'], 410);
        }

        $signer->update([
            'user_id' => $user->id,
            'invite_accepted_at' => now(),
            'invite_token' => null,
            'invite_expires_at' => null,
        ]);

        return response()->json([
            'status' => 'accepted',
            'documentId' => $signer->document_id,
            'signerId' => $signer->id,
        ]);
    }
}
