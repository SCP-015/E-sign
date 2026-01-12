<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use App\Mail\DocumentAssignmentInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SignerController extends Controller
{
    /**
     * Add signer to document
     * POST /api/documents/{documentId}/signers
     */
    public function store(Request $request, $documentId)
    {
        $request->validate([
            'signers' => 'required|array|min:1',
            'signers.*.email' => 'required|email',
            'signers.*.name' => 'required|string|max:255',
            'signers.*.order' => 'nullable|integer',
        ]);

        $document = Document::where('id', $documentId)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            $signers = [];
            foreach ($request->input('signers') as $signerData) {
                $email = $signerData['email'];
                $user = User::where('email', $email)->first();

                do {
                    $inviteToken = Str::random(16);
                } while (DocumentSigner::where('invite_token', $inviteToken)->exists());
                $inviteExpiresAt = now()->addDays(7);

                $signer = DocumentSigner::create([
                    'document_id' => $document->id,
                    'user_id' => $user?->id,
                    'email' => $email,
                    'name' => $signerData['name'],
                    'invite_token' => $inviteToken,
                    'invite_expires_at' => $inviteExpiresAt,
                    'invite_accepted_at' => null,
                    'order' => $signerData['order'] ?? null,
                    'status' => 'PENDING',
                ]);
                
                $signers[] = $signer;

                // Send invitation email
                Mail::to($email)->send(new DocumentAssignmentInvitation(
                    $document,
                    $email,
                    $inviteToken,
                    $request->user()->name
                ));
            }

            // Update document status to IN_PROGRESS
            $document->update(['status' => 'IN_PROGRESS']);

            DB::commit();

            return response()->json([
                'documentId' => $document->id,
                'status' => $document->status,
                'signers' => collect($signers)->map(fn($s) => [
                    'id' => $s->id,
                    'userId' => $s->user_id,
                    'email' => $s->email,
                    'name' => $s->name,
                    'order' => $s->order,
                ])->toArray(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add signers: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get document signers
     * GET /api/documents/{documentId}/signers
     */
    public function index($documentId)
    {
        $document = Document::findOrFail($documentId);
        
        $signers = $document->signers()->with('user')->get();

        return response()->json([
            'documentId' => $document->id,
            'status' => $document->status,
            'signers' => $signers->map(fn($s) => [
                'id' => $s->id,
                'userId' => $s->user_id,
                'name' => $s->name,
                'order' => $s->order,
                'signedAt' => $s->signed_at?->toIso8601String(),
            ])->toArray(),
        ]);
    }
}
