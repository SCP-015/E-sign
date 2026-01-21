<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentSigner;
use App\Models\SignaturePlacement;
use App\Models\Signature;
use App\Models\User;
use App\Models\Certificate;
use Illuminate\Support\Facades\DB;

class PlacementService
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

    protected function findDocumentOrFail(string $documentId): Document
    {
        $tenantId = $this->getCurrentTenantId();
        if ($tenantId) {
            return Document::query()->tenant($tenantId)->where('id', $documentId)->firstOrFail();
        }

        return Document::query()->where('id', $documentId)->firstOrFail();
    }

    public function storePlacements(string $documentId, ?string $signerUserId, ?string $email, array $placements): array
    {
        try {
            $document = $this->findDocumentOrFail($documentId);

            $existingSignersCount = DocumentSigner::where('document_id', $documentId)->count();
            
            $signer = null;
            if ($signerUserId) {
                $signer = DocumentSigner::where('document_id', $documentId)
                    ->where('user_id', $signerUserId)
                    ->first();

                if (!$signer) {
                    $signerUser = User::find($signerUserId);
                    if ($signerUser) {
                        $signerEmail = strtolower($signerUser->email);
                        $signer = DocumentSigner::where('document_id', $documentId)
                            ->whereRaw('LOWER(email) = ?', [$signerEmail])
                            ->first();

                        if ($signer) {
                            $signer->update([
                                'user_id' => $signerUser->id,
                                'email' => $signerEmail,
                                'name' => $signerUser->name,
                            ]);
                        }
                    }
                }
            } elseif ($email) {
                $email = strtolower(trim((string) $email));
                $signer = DocumentSigner::where('document_id', $documentId)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();

                if ($signer && !$signer->user_id) {
                    $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
                    if ($user) {
                        $signer->update([
                            'user_id' => $user->id,
                            'email' => strtolower($user->email),
                            'name' => $user->name,
                        ]);
                    }
                }
            }
            
            if (!$signer) {
                // If there are existing signers (assignment flow), do not allow creating a new signer implicitly.
                if ($existingSignersCount > 0) {
                    return [
                        'status' => 'error',
                        'code' => 403,
                        'message' => 'Signer is not assigned to this document',
                        'data' => null,
                    ];
                }

                if ($signerUserId) {
                    $signerUser = User::findOrFail($signerUserId);
                    $signer = DocumentSigner::create([
                        'document_id' => $documentId,
                        'user_id' => $signerUserId,
                        'email' => strtolower($signerUser->email),
                        'name' => $signerUser->name,
                        'status' => 'PENDING',
                    ]);
                } elseif ($email) {
                    $signer = DocumentSigner::create([
                        'document_id' => $documentId,
                        'email' => strtolower(trim((string) $email)),
                        'name' => 'Signer',
                        'status' => 'PENDING',
                    ]);
                }
                
                $document->update(['status' => 'IN_PROGRESS']);
            }

            DB::beginTransaction();
            
            SignaturePlacement::where('document_id', $documentId)
                ->where('signer_id', $signer->id)
                ->delete();

            $placementRecords = [];
            $hasSignature = false;
            
            foreach ($placements as $placementData) {
                $signatureId = $placementData['signatureId'] ?? null;
                
                if ($signatureId && $signer->user_id) {
                    $signature = Signature::where('id', $signatureId)
                        ->where('user_id', $signer->user_id)
                        ->firstOrFail();
                    $hasSignature = true;
                }

                $placement = SignaturePlacement::create([
                    'document_id' => $documentId,
                    'signer_id' => $signer->id,
                    'signature_id' => $signatureId,
                    'page' => $placementData['page'],
                    'x' => $placementData['x'],
                    'y' => $placementData['y'],
                    'w' => $placementData['w'],
                    'h' => $placementData['h'],
                ]);
                $placementRecords[] = $placement;
            }

            if ($hasSignature) {
                $signingMode = strtoupper((string) ($document->signing_mode ?? 'PARALLEL'));
                if ($signingMode === 'SEQUENTIAL') {
                    // Sequential signing enforcement: only allow the earliest pending signer to sign.
                    $nextSigner = DocumentSigner::where('document_id', $documentId)
                        ->where('status', '!=', 'SIGNED')
                        ->orderByRaw('"order" IS NULL')
                        ->orderBy('order')
                        ->orderBy('id')
                        ->first();

                    if ($nextSigner && $nextSigner->id !== $signer->id) {
                        DB::rollBack();

                        return [
                            'status' => 'error',
                            'code' => 409,
                            'message' => 'Not your turn to sign yet',
                            'data' => [
                                'nextSignerId' => $nextSigner->id,
                                'nextSignerEmail' => $nextSigner->email,
                                'nextSignerOrder' => $nextSigner->order,
                            ],
                        ];
                    }
                }

                $signer->update([
                    'status' => 'SIGNED',
                    'signed_at' => now(),
                ]);

                $allSigned = $document->signers()
                    ->where('status', '!=', 'SIGNED')
                    ->count() === 0;
                
                if ($allSigned) {
                    $verifyToken = $document->verify_token ?? bin2hex(random_bytes(16));

                    // If this was a self-sign flow (no prior signers assigned), auto-finalize
                    if ($existingSignersCount === 0) {
                        // Generate final PDF with QR and mark as COMPLETED
                        $qrConfig = [
                            'page' => 'LAST',
                            'position' => 'BOTTOM_RIGHT',
                            'marginBottom' => 15,
                            'marginRight' => 15,
                            'size' => 35,
                        ];

                        $finalPdfPath = app(\App\Services\DocumentService::class)
                            ->finalizePdf($document, $verifyToken, $qrConfig);

                        $document->update([
                            'status' => 'COMPLETED',
                            'completed_at' => now(),
                            'final_pdf_path' => $finalPdfPath,
                            'verify_token' => $verifyToken,
                        ]);

                        // Upsert signing evidence (owner certificate)
                        $tenantId = $this->getCurrentTenantId();
                        $certQuery = Certificate::query();
                        if ($tenantId) {
                            $certQuery = $certQuery->tenant($tenantId);
                        }

                        $cert = $certQuery->where('user_id', $document->user_id)
                            ->where('status', 'active')
                            ->orderByDesc('issued_at')
                            ->orderByDesc('created_at')
                            ->first();
                        if ($cert) {
                            app(\App\Services\DocumentService::class)
                                ->upsertSigningEvidence($document->fresh(), $cert, $finalPdfPath, now());
                        }
                    } else {
                        // Multi-signer: keep as signed; requires explicit finalize by owner
                        $document->update([
                            'status' => 'signed',
                            'verify_token' => $verifyToken,
                        ]);
                    }
                }
            }

            DB::commit();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'Placements saved successfully',
                'data' => [
                    'documentId' => $document->id,
                    'signerId' => $signer->id,
                    'placements' => collect($placementRecords)->map(fn($p) => [
                        'placementId' => $p->id,
                        'page' => $p->page,
                        'x' => $p->x,
                        'y' => $p->y,
                        'w' => $p->w,
                        'h' => $p->h,
                        'signatureId' => $p->signature_id,
                    ])->toArray(),
                    'signerStatus' => $signer->status,
                    'documentStatus' => $document->status,
                    'needsFinalize' => isset($allSigned)
                        ? ($existingSignersCount === 0 ? false : (bool) $allSigned)
                        : false,
                    'verifyToken' => isset($verifyToken) ? $verifyToken : null,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return [
                'status' => 'error',
                'code' => 500,
                'message' => __('Failed to save placement: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }

    public function updatePlacement(string $documentId, string $placementId, array $data): array
    {
        try {
            $placement = SignaturePlacement::where('id', $placementId)
                ->where('document_id', $documentId)
                ->firstOrFail();

            $placement->update(array_filter($data, fn($value) => $value !== null));

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'Placement updated successfully',
                'data' => [
                    'placementId' => $placement->id,
                    'page' => $placement->page,
                    'x' => $placement->x,
                    'y' => $placement->y,
                    'w' => $placement->w,
                    'h' => $placement->h,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => __('Placement not found: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }

    public function getPlacements(string $documentId): array
    {
        try {
            $document = $this->findDocumentOrFail($documentId);
            
            $placements = $document->placements()
                ->with(['signer', 'signature'])
                ->get();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'documentId' => $document->id,
                    'placements' => $placements->map(fn($p) => [
                        'placementId' => $p->id,
                        'signerUserId' => $p->signer->user_id,
                        'signerName' => $p->signer->name,
                        'page' => $p->page,
                        'x' => $p->x,
                        'y' => $p->y,
                        'w' => $p->w,
                        'h' => $p->h,
                        'signatureId' => $p->signature_id,
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
