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
    protected function getCurrentTenantId(): ?string
    {
        $tenantId = config('tenant.currentTenantId');
        if (is_string($tenantId) && $tenantId !== '') {
            return $tenantId;
        }

        $tenantId = session('current_tenant_id');
        return is_string($tenantId) && $tenantId !== '' ? $tenantId : null;
    }

    protected function findOwnerDocumentOrFail(string $documentId, string $ownerUserId): Document
    {
        $tenantId = $this->getCurrentTenantId();
        $query = Document::query();

        if ($tenantId) {
            $query = $query->tenant($tenantId);
        }

        return $query->where('id', $documentId)
            ->where('user_id', $ownerUserId)
            ->firstOrFail();
    }

    protected function findDocumentOrFail(string $documentId): Document
    {
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId) {
            return Document::query()->tenant($tenantId)->where('id', $documentId)->firstOrFail();
        }

        return Document::query()->where('id', $documentId)->firstOrFail();
    }

    public function addSigners(string $documentId, string $ownerUserId, array $signersInput, array $options = []): array
    {
        try {
            $document = $this->findOwnerDocumentOrFail($documentId, $ownerUserId);

            $owner = User::findOrFail($ownerUserId);

            DB::beginTransaction();

            $ownerEmail = strtolower(trim((string) $owner->email));
            $includeOwner = (bool) ($options['include_owner'] ?? false);
            $ownerOrder = $options['owner_order'] ?? null;
            $signingMode = $options['signing_mode'] ?? null;

            if (is_string($signingMode) && $signingMode !== '') {
                $mode = strtoupper(trim($signingMode));
                if (in_array($mode, ['PARALLEL', 'SEQUENTIAL'], true)) {
                    $document->update(['signing_mode' => $mode]);
                }
            }

            if ($includeOwner) {
                $existingOwner = DocumentSigner::where('document_id', $document->id)
                    ->where(function ($q) use ($ownerUserId, $ownerEmail) {
                        $q->where('user_id', $ownerUserId)
                            ->orWhereRaw('LOWER(email) = ?', [$ownerEmail]);
                    })
                    ->first();

                if ($existingOwner) {
                    $existingOwner->update([
                        'user_id' => $ownerUserId,
                        'email' => $ownerEmail,
                        'name' => $owner->name,
                        'order' => is_null($ownerOrder) ? $existingOwner->order : (int) $ownerOrder,
                    ]);
                } else {
                    DocumentSigner::create([
                        'document_id' => $document->id,
                        'user_id' => $ownerUserId,
                        'email' => $ownerEmail,
                        'name' => $owner->name,
                        'order' => is_null($ownerOrder) ? null : (int) $ownerOrder,
                        'status' => 'PENDING',
                    ]);
                }
            }
            
            $signers = [];
            foreach ($signersInput as $signerData) {
                $email = strtolower(trim((string) $signerData['email']));
                if ($email === $ownerEmail) {
                    // Owner included via signers payload: upsert without sending invitation
                    $existingOwner = DocumentSigner::where('document_id', $document->id)
                        ->where(function ($q) use ($ownerUserId, $ownerEmail) {
                            $q->where('user_id', $ownerUserId)
                                ->orWhereRaw('LOWER(email) = ?', [$ownerEmail]);
                        })
                        ->first();

                    if ($existingOwner) {
                        $existingOwner->update([
                            'user_id' => $ownerUserId,
                            'email' => $ownerEmail,
                            'name' => $owner->name,
                            'order' => isset($signerData['order']) ? (int) $signerData['order'] : $existingOwner->order,
                            'status' => 'PENDING',
                        ]);
                        $signers[] = $existingOwner;
                    } else {
                        $signers[] = DocumentSigner::create([
                            'document_id' => $document->id,
                            'user_id' => $ownerUserId,
                            'email' => $ownerEmail,
                            'name' => $owner->name,
                            'order' => isset($signerData['order']) ? (int) $signerData['order'] : null,
                            'status' => 'PENDING',
                        ]);
                    }

                    continue;
                }
                $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
                $displayName = $user?->name ?? $signerData['name'];

                do {
                    $inviteToken = Str::random(16);
                } while (DocumentSigner::where('invite_token', $inviteToken)->exists());
                
                $inviteExpiresAt = now()->addDays(7);

                $existingSigner = DocumentSigner::where('document_id', $document->id)
                    ->where(function ($q) use ($user, $email) {
                        if ($user) {
                            $q->where('user_id', $user->id);
                        }
                        $q->orWhereRaw('LOWER(email) = ?', [$email]);
                    })
                    ->first();

                if ($existingSigner) {
                    $existingSigner->update([
                        'user_id' => $user?->id,
                        'email' => $email,
                        'name' => $displayName,
                        'invite_token' => $inviteToken,
                        'invite_expires_at' => $inviteExpiresAt,
                        'invite_accepted_at' => null,
                        'order' => $signerData['order'] ?? $existingSigner->order,
                        'status' => 'PENDING',
                    ]);
                    $signer = $existingSigner;
                } else {
                    $signer = DocumentSigner::create([
                        'document_id' => $document->id,
                        'user_id' => $user?->id,
                        'email' => $email,
                        'name' => $displayName,
                        'invite_token' => $inviteToken,
                        'invite_expires_at' => $inviteExpiresAt,
                        'invite_accepted_at' => null,
                        'order' => $signerData['order'] ?? null,
                        'status' => 'PENDING',
                    ]);
                }

                $signers[] = $signer;

                Mail::to($email)->send(new DocumentAssignmentInvitation(
                    $document,
                    $email,
                    $inviteToken,
                    $owner->name
                ));
            }

            // Normalize signer order to sequential 1..N based on provided order (nulls last)
            $allSigners = DocumentSigner::where('document_id', $document->id)
                ->orderByRaw('"order" IS NULL')
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            $seq = 1;
            foreach ($allSigners as $s) {
                $s->update(['order' => $seq]);
                $seq++;
            }

            $signers = $allSigners;

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
                'message' => __('Failed to add signers: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }

    public function getSigners(string $documentId): array
    {
        try {
            $document = $this->findDocumentOrFail($documentId);
            $signers = $document->signers()->with('user')->get();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'document_id' => $document->id,
                    'status' => $document->status,
                    'signers' => $signers->map(fn ($s) => [
                        'id' => $s->id,
                        'user_id' => $s->user_id,
                        'email' => $s->email,
                        'name' => $s->user?->name ?? $s->name,
                        'order' => $s->order,
                        'signed_at' => $s->signed_at?->toIso8601String(),
                    ])->toArray(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => __('Document not found: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }
}
