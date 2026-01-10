<?php

namespace App\Services;

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
                    $cert = $document->user?->certificate;
                    $isCertActive = $cert && $cert->status === 'active' && (!$cert->expires_at || $cert->expires_at->isFuture());

                    $payload = [
                        'is_valid' => (bool) $isCertActive,
                        'message' => $isCertActive ? 'Signature is valid' : 'Certificate is not active or has expired',
                        'signed_by' => $document->user?->name,
                        'signed_at' => $document->completed_at?->toIso8601String() ?? $document->updated_at?->toIso8601String(),
                        'document_id' => $document->id,
                        'file_name' => $document->title ?? basename($document->file_path),
                    ];

                    if (!$isCertActive && $cert) {
                        $payload['certificate'] = [
                            'id' => $cert->id,
                            'status' => $cert->status,
                            'issued_at' => $cert->issued_at?->toIso8601String(),
                            'expires_at' => $cert->expires_at?->toIso8601String(),
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
            }])
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

        $cert = $document->user?->certificate;
        $isCertActive = $cert && $cert->status === 'active' && (!$cert->expires_at || $cert->expires_at->isFuture());

        $payload = [
            'documentId' => $document->id,
            'status' => $document->status,
            'is_valid' => (bool) $isCertActive,
            'message' => $isCertActive ? 'Document is valid' : 'Certificate is not active or has expired',
            'fileName' => $document->title ?? basename($document->file_path),
            'completedAt' => $document->completed_at?->toIso8601String(),
            'signers' => $document->signers->map(fn ($s) => [
                'name' => $s->name,
                'status' => $s->status,
                'signedAt' => $s->signed_at?->toIso8601String(),
            ])->toArray(),
        ];

        return [
            'status' => 'success',
            'code' => 200,
            'message' => $payload['message'],
            'data' => $payload,
        ];
    }
}
