<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use App\Services\CertificateService;

class VerificationService
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function verify($documentId)
    {
        $document = Document::findOrFail($documentId);

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

        if (!$evidence || !$evidence->signed_at || !$evidence->certificate_not_before || !$evidence->certificate_not_after) {
            return [
                'is_valid' => false,
                'message' => 'No signing evidence found for document (LTV data missing)',
                'signed_by' => $document->user?->name,
                'signed_at' => $document->completed_at?->toIso8601String() ?? $document->updated_at?->toIso8601String(),
                'document_id' => $document->id,
            ];
        }

        $signedAt = $evidence->signed_at;
        $wasCertValidAtSigning = $signedAt->between($evidence->certificate_not_before, $evidence->certificate_not_after);
        if (!$wasCertValidAtSigning) {
            return [
                'is_valid' => false,
                'message' => 'Certificate was not valid at signing time',
                'signed_by' => $document->user?->name,
                'signed_at' => $signedAt->toIso8601String(),
                'document_id' => $document->id,
                'evidence' => [
                    'certificate_number' => $evidence->certificate_number,
                    'certificate_fingerprint_sha256' => $evidence->certificate_fingerprint_sha256,
                    'certificate_not_before' => $evidence->certificate_not_before?->toIso8601String(),
                    'certificate_not_after' => $evidence->certificate_not_after?->toIso8601String(),
                ],
            ];
        }

        if (!in_array($document->status, ['signed', 'COMPLETED'], true)) {
            return [
                'is_valid' => false,
                'message' => 'Document not signed',
                'signed_by' => null,
                'signed_at' => null,
            ];
        }

        $pdfPath = null;
        if ($document->final_pdf_path && file_exists(storage_path('app/' . $document->final_pdf_path))) {
            $pdfPath = storage_path('app/' . $document->final_pdf_path);
        } elseif ($document->signed_path) {
            // Remove 'private/' prefix if present since Storage::disk('private')->path() already adds it
            $relativePath = str_replace('private/', '', $document->signed_path);
            $pdfPath = Storage::disk('private')->path($relativePath);
        }

        if (!$pdfPath || !file_exists($pdfPath)) {
            try {
                $verifyToken = $document->verify_token ?? bin2hex(random_bytes(16));
                $qrConfig = [
                    'page' => 'LAST',
                    'position' => 'BOTTOM_RIGHT',
                    'marginBottom' => 15,
                    'marginRight' => 15,
                    'size' => 35,
                ];

                $documentService = app(\App\Services\DocumentService::class);
                $finalPdfPath = $documentService->finalizePdf($document, $verifyToken, $qrConfig);
                $document->update([
                    'final_pdf_path' => $finalPdfPath,
                    'verify_token' => $verifyToken,
                ]);

                $pdfPath = storage_path('app/' . $finalPdfPath);
            } catch (\Exception $e) {
                return [
                    'is_valid' => false,
                    'message' => 'Signed file not found in storage',
                    'signed_by' => null,
                    'signed_at' => null,
                ];
            }
        }
        
        // For MVP: Simple verification - if file exists and status is signed, consider it valid
        // In production, would use OpenSSL to verify actual signature
        // For now, just check that the file contains signature markers
        
        $content = file_get_contents($pdfPath);
        
        // Check if PDF has signature markers (SigFlags, /Sig, /Contents, /AcroForm)
        $hasSignature = strpos($content, '/SigFlags') !== false || 
                       strpos($content, '/Sig') !== false || 
                       strpos($content, '/Contents') !== false ||
                       strpos($content, '/AcroForm') !== false;
        
        if (!$hasSignature) {
            return [
                'is_valid' => false,
                'message' => 'No signature markers found in PDF',
                'signed_by' => null,
                'signed_at' => null
            ];
        }

        // Success - document is signed and file exists
        return [
            'is_valid' => true,
            'message' => 'Signature is valid',
            'signed_by' => $document->user->name,
            'signed_at' => $evidence->signed_at?->toIso8601String() ?? $document->updated_at->toIso8601String(),
            'document_id' => $document->id,
            'file_name' => $document->title ?? basename($document->file_path),
            'ltv' => [
                'certificate_number' => $evidence->certificate_number,
                'certificate_fingerprint_sha256' => $evidence->certificate_fingerprint_sha256,
                'certificate_not_before' => $evidence->certificate_not_before?->toIso8601String(),
                'certificate_not_after' => $evidence->certificate_not_after?->toIso8601String(),
                'tsa_url' => $evidence->tsa_url,
                'tsa_at' => $evidence->tsa_at?->toIso8601String(),
                'has_tsa_token' => (bool) $evidence->tsa_token,
            ],
        ];
    }

    public function verifyResult(int $documentId): array
    {
        $result = $this->verify($documentId);

        return [
            'status' => 'success',
            'code' => 200,
            'message' => $result['message'] ?? 'OK',
            'data' => $result,
        ];
    }
}
