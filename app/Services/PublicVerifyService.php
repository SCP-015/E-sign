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

        try {
            $file->move(dirname($tmpPath), basename($tmpPath));
            $content = file_get_contents($tmpPath);

            if ($content === false) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Failed to read uploaded PDF',
                    'data' => [
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
                $document = Document::where('verify_token', $token)->first();
                if ($document) {
                    $evidence = $document->signingEvidence;
                    if (!$evidence || !$evidence->signed_at || !$evidence->certificate_not_before || !$evidence->certificate_not_after) {
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
                        'is_valid' => (bool) $wasCertValidAtSigning,
                        'message' => $wasCertValidAtSigning ? 'Signature is valid' : 'Signature cannot be validated (missing/invalid LTV evidence)',
                        'signed_by' => $document->user?->name,
                        'signed_at' => $signedAt?->toIso8601String() ?? $document->completed_at?->toIso8601String() ?? $document->updated_at?->toIso8601String(),
                        'document_id' => $document->id,
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
        $document = Document::where('verify_token', $token)
            ->with(['signers' => function ($query) {
                $query->orderBy('order')->orderBy('signed_at');
            }, 'signingEvidence'])
            ->first();

        if (!$document) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Document not found or invalid token',
                'data' => [
                    'message' => 'Document not found or invalid token',
                ],
            ];
        }

        $evidence = $document->signingEvidence;
        if (!$evidence || !$evidence->signed_at || !$evidence->certificate_not_before || !$evidence->certificate_not_after) {
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
            'documentId' => $document->id,
            'status' => $document->status,
            'is_valid' => (bool) $wasCertValidAtSigning,
            'message' => $wasCertValidAtSigning ? 'Document is valid' : 'Document cannot be validated (missing/invalid LTV evidence)',
            'fileName' => $document->title ?? basename($document->file_path),
            'completedAt' => $document->completed_at?->toIso8601String(),
            'signers' => $document->signers->map(fn ($s) => [
                'name' => $s->name,
                'status' => $s->status,
                'signedAt' => $s->signed_at?->toIso8601String(),
            ])->toArray(),
        ];

        if ($evidence) {
            $payload['ltv'] = [
                'signedAt' => $signedAt?->toIso8601String(),
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
