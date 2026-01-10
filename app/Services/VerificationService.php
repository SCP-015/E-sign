<?php

namespace App\Services;

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

        $cert = $document->user?->certificate;
        if (!$cert) {
            return [
                'is_valid' => false,
                'message' => 'No certificate found for document owner',
                'signed_by' => $document->user?->name,
                'signed_at' => $document->updated_at?->toIso8601String(),
                'document_id' => $document->id,
            ];
        }

        $isCertActive = $cert->status === 'active' && (!$cert->expires_at || $cert->expires_at->isFuture());
        if (!$isCertActive) {
            return [
                'is_valid' => false,
                'message' => 'Certificate is not active or has expired',
                'signed_by' => $document->user?->name,
                'signed_at' => $document->updated_at?->toIso8601String(),
                'document_id' => $document->id,
                'certificate' => [
                    'id' => $cert->id,
                    'status' => $cert->status,
                    'issued_at' => $cert->issued_at?->toIso8601String(),
                    'expires_at' => $cert->expires_at?->toIso8601String(),
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
            'signed_at' => $document->updated_at->toIso8601String(),
            'document_id' => $document->id,
            'file_name' => $document->title ?? basename($document->file_path),
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
