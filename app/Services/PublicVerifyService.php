<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Document;
use Illuminate\Http\UploadedFile;

class PublicVerifyService
{
    public function verifyUpload(UploadedFile $file): array
    {
        $tmpPath = sys_get_temp_dir() . '/verify_upload_' . uniqid() . '.pdf';
        $verifiedAt = now()->toIso8601String();

        try {
            $file->move(dirname($tmpPath), basename($tmpPath));
            $content = file_get_contents($tmpPath);

            if ($content === false) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Failed to read uploaded PDF',
                    'data' => [
                        'verified_at' => $verifiedAt,
                        'is_valid' => false,
                        'message' => 'Failed to read uploaded PDF',
                        'signed_by' => null,
                        'signed_at' => null,
                        'document_id' => null,
                    ],
                ];
            }

            $hasSignature = (bool) preg_match('/\\/ByteRange\s*\\[\s*\\d+\s+\\d+\s+\\d+\s+\\d+\s*\\]/', $content);

            if (!$hasSignature) {
                $payload = [
                    'verified_at' => $verifiedAt,
                    'is_valid' => false,
                    'message' => 'No digital signature found in PDF',
                    'signed_by' => null,
                    'signed_at' => null,
                    'document_id' => null,
                    'file_name' => $file->getClientOriginalName(),
                ];

                return [
                    'status' => 'success',
                    'code' => 200,
                    'message' => $payload['message'],
                    'data' => $payload,
                ];
            }

            $token = null;
            if (preg_match('#/api/verify/([a-f0-9]{32})#i', $content, $m)) {
                $token = $m[1];
            } elseif (preg_match('#/verify/([a-f0-9]{32})#i', $content, $m)) {
                $token = $m[1];
            } elseif (preg_match('/VERIFY_TOKEN=([a-f0-9]{32})/i', $content, $m)) {
                $token = $m[1];
            }

            if ($token) {
                $document = Document::where('verify_token', $token)
                    ->with(['user', 'signers.user', 'signingEvidence'])
                    ->first();
                if ($document) {
                    $evidence = $document->signingEvidence;
                    $allowBackfill = filter_var(env('LTV_BACKFILL_ON_DEMAND', false), FILTER_VALIDATE_BOOLEAN);
                    $canBackfill = $allowBackfill
                        && $document->status === 'COMPLETED'
                        && !empty($document->final_pdf_path);

                    if ($canBackfill && (!$evidence || !$evidence->signed_at || !$evidence->certificate_not_before || !$evidence->certificate_not_after)) {
                        $signedAtFallback = $document->completed_at ?? $document->updated_at;
                        $cert = Certificate::where('user_id', (int) $document->user_id)
                            ->where(function ($q) use ($signedAtFallback) {
                                $q->whereNull('issued_at')->orWhere('issued_at', '<=', $signedAtFallback);
                            })
                            ->where(function ($q) use ($signedAtFallback) {
                                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $signedAtFallback);
                            })
                            ->orderByDesc('issued_at')
                            ->orderByDesc('created_at')
                            ->first();

                        if (!$cert) {
                            $cert = Certificate::where('user_id', (int) $document->user_id)
                                ->orderByDesc('issued_at')
                                ->orderByDesc('created_at')
                                ->first();
                        }

                        if ($cert) {
                            app(\App\Services\DocumentService::class)->upsertSigningEvidence($document->fresh(), $cert, $document->final_pdf_path);
                            $document->load('signingEvidence');
                            $evidence = $document->signingEvidence;
                        }
                    }

                    $signedAt = $evidence?->signed_at;
                    $wasCertValidAtSigning = false;
                    if ($evidence && $signedAt && $evidence->certificate_not_before && $evidence->certificate_not_after) {
                        $wasCertValidAtSigning = $signedAt->between($evidence->certificate_not_before, $evidence->certificate_not_after);
                    }

                    $signedBy = null;
                    $signedEmail = null;
                    $signedSigner = $document->signers
                        ?->first(fn ($s) => $s->status === 'SIGNED' && $s->signed_at);
                    if ($signedSigner) {
                        $signedBy = $signedSigner->user?->name ?? $signedSigner->name;
                        $signedEmail = $signedSigner->user?->email ?? $signedSigner->email;
                    }
                    $signedBy = $signedBy ?? $document->user?->name;
                    $signedEmail = $signedEmail ?? $document->user?->email;

                    $documentOwner = $document->user
                        ? [
                            'id' => (int) $document->user->id,
                            'name' => $document->user->name,
                            'email' => $document->user->email,
                            'avatar' => $document->user->avatar,
                        ]
                        : null;

                    $payload = [
                        'verified_at' => $verifiedAt,
                        'is_valid' => (bool) $wasCertValidAtSigning,
                        'message' => $wasCertValidAtSigning ? 'Signature is valid' : 'Signature cannot be validated (missing/invalid LTV evidence)',
                        'signed_by' => $signedBy,
                        'signed_email' => $signedEmail,
                        'signed_at' => $signedAt?->toIso8601String() ?? $document->completed_at?->toIso8601String() ?? $document->updated_at?->toIso8601String(),
                        'document_id' => $document->id,
                        'document_owner' => $documentOwner,
                        'file_name' => $document->title ?? basename($document->file_path),
                    ];

                    if ($evidence) {
                        $payload['ltv'] = [
                            'certificate_number' => $evidence->certificate_number,
                            'certificate_fingerprint_sha256' => $evidence->certificate_fingerprint_sha256,
                            'certificate_not_before' => $evidence->certificate_not_before?->toIso8601String(),
                            'certificate_not_after' => $evidence->certificate_not_after?->toIso8601String(),
                            'tsa_url' => $evidence->tsa_url,
                            'tsa_at' => $evidence->tsa_at?->toIso8601String(),
                            'has_tsa_token' => (bool) $evidence->tsa_token,
                        ];
                    }

                    return [
                        'status' => 'success',
                        'code' => 200,
                        'message' => $payload['message'],
                        'data' => $payload,
                    ];
                }
            }

            $payload = [
                'verified_at' => $verifiedAt,
                'is_valid' => true,
                'message' => 'Signature is valid (signer identity unknown)',
                'signed_by' => null,
                'signed_at' => null,
                'document_id' => null,
                'file_name' => $file->getClientOriginalName(),
            ];

            return [
                'status' => 'success',
                'code' => 200,
                'message' => $payload['message'],
                'data' => $payload,
            ];
        } catch (\Exception $e) {
            $payload = [
                'verified_at' => now()->toIso8601String(),
                'is_valid' => false,
                'message' => 'Verification failed: ' . $e->getMessage(),
                'signed_by' => null,
                'signed_at' => null,
                'document_id' => null,
            ];

            return [
                'status' => 'error',
                'code' => 500,
                'message' => $payload['message'],
                'data' => $payload,
            ];
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    public function verifyToken(string $token): array
    {
        $verifiedAt = now()->toIso8601String();
        $document = Document::where('verify_token', $token)
            ->with(['signers' => function ($query) {
                $query->orderBy('order')->orderBy('signed_at');
            }, 'user', 'signers.user', 'signingEvidence'])
            ->first();

        if (!$document) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Document not found or invalid token',
                'data' => [
                    'verified_at' => $verifiedAt,
                    'message' => 'Document not found or invalid token',
                ],
            ];
        }

        $evidence = $document->signingEvidence;
        $allowBackfill = filter_var(env('LTV_BACKFILL_ON_DEMAND', false), FILTER_VALIDATE_BOOLEAN);
        $canBackfill = $allowBackfill
            && $document->status === 'COMPLETED'
            && !empty($document->final_pdf_path);

        if ($canBackfill && (!$evidence || !$evidence->signed_at || !$evidence->certificate_not_before || !$evidence->certificate_not_after)) {
            $signedAtFallback = $document->completed_at ?? $document->updated_at;
            $cert = Certificate::where('user_id', (int) $document->user_id)
                ->where(function ($q) use ($signedAtFallback) {
                    $q->whereNull('issued_at')->orWhere('issued_at', '<=', $signedAtFallback);
                })
                ->where(function ($q) use ($signedAtFallback) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', $signedAtFallback);
                })
                ->orderByDesc('issued_at')
                ->orderByDesc('created_at')
                ->first();

            if (!$cert) {
                $cert = Certificate::where('user_id', (int) $document->user_id)
                    ->orderByDesc('issued_at')
                    ->orderByDesc('created_at')
                    ->first();
            }

            if ($cert) {
                app(\App\Services\DocumentService::class)->upsertSigningEvidence($document->fresh(), $cert, $document->final_pdf_path);
                $document->load('signingEvidence');
                $evidence = $document->signingEvidence;
            }
        }
        $signedAt = $evidence?->signed_at;
        $wasCertValidAtSigning = false;
        if ($evidence && $signedAt && $evidence->certificate_not_before && $evidence->certificate_not_after) {
            $wasCertValidAtSigning = $signedAt->between($evidence->certificate_not_before, $evidence->certificate_not_after);
        }

        $payload = [
            'verified_at' => $verifiedAt,
            'document_id' => $document->id,
            'status' => $document->status,
            'is_valid' => (bool) $wasCertValidAtSigning,
            'message' => $wasCertValidAtSigning ? 'Document is valid' : 'Document cannot be validated (missing/invalid LTV evidence)',
            'document_owner' => $document->user
                ? [
                    'id' => (int) $document->user->id,
                    'name' => $document->user->name,
                    'email' => $document->user->email,
                    'avatar' => $document->user->avatar,
                ]
                : null,
            'file_name' => $document->title ?? basename($document->file_path),
            'completed_at' => $document->completed_at?->toIso8601String(),
            'signers' => $document->signers->map(fn ($s) => [
                'name' => $s->user?->name ?? $s->name,
                'email' => $s->user?->email ?? $s->email,
                'status' => $s->status,
                'signed_at' => $s->signed_at?->toIso8601String(),
            ])->toArray(),
        ];

        if ($evidence) {
            $payload['ltv'] = [
                'signed_at' => $signedAt?->toIso8601String(),
                'certificate_number' => $evidence->certificate_number,
                'certificate_fingerprint_sha256' => $evidence->certificate_fingerprint_sha256,
                'certificate_not_before' => $evidence->certificate_not_before?->toIso8601String(),
                'certificate_not_after' => $evidence->certificate_not_after?->toIso8601String(),
                'tsa_url' => $evidence->tsa_url,
                'tsa_at' => $evidence->tsa_at?->toIso8601String(),
                'has_tsa_token' => (bool) $evidence->tsa_token,
            ];
        }

        return [
            'status' => 'success',
            'code' => 200,
            'message' => $payload['message'],
            'data' => $payload,
        ];
    }
}
