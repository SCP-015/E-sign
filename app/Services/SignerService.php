<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\User;
use App\Mail\DocumentAssignmentInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SignerService
{
    public function addSigners(int $documentId, int $ownerUserId, array $signersInput): array
    {
        try {
            $document = Document::where('id', $documentId)
                ->where('user_id', $ownerUserId)
                ->firstOrFail();

            $owner = User::findOrFail($ownerUserId);

            DB::beginTransaction();
            
            $signers = [];
            foreach ($signersInput as $signerData) {
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

                Mail::to($email)->send(new DocumentAssignmentInvitation(
                    $document,
                    $email,
                    $inviteToken,
                    $owner->name
                ));
            }

            $document->update(['status' => 'IN_PROGRESS']);

            DB::commit();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'Signers added successfully',
                'data' => [
                    'documentId' => $document->id,
                    'status' => $document->status,
                    'signers' => collect($signers)->map(fn ($s) => [
                        'id' => $s->id,
                        'userId' => $s->user_id,
                        'email' => $s->email,
                        'name' => $s->name,
                        'order' => $s->order,
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to add signers: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function getSigners(int $documentId): array
    {
        try {
            $document = Document::findOrFail($documentId);
            $signers = $document->signers()->with('user')->get();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'documentId' => $document->id,
                    'status' => $document->status,
                    'signers' => $signers->map(fn ($s) => [
                        'id' => $s->id,
                        'userId' => $s->user_id,
                        'name' => $s->name,
                        'order' => $s->order,
                        'signedAt' => $s->signed_at?->toIso8601String(),
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Document not found: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
