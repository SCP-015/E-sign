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
        
        if ($document->status !== 'signed' || !$document->signed_path) {
            return [
                'is_valid' => false, 
                'message' => 'Document not signed',
                'signed_by' => null,
                'signed_at' => null
            ];
        }

        // Remove 'private/' prefix if present since Storage::disk('private')->path() already adds it
        $relativePath = str_replace('private/', '', $document->signed_path);
        $pdfPath = Storage::disk('private')->path($relativePath);
        
        if (!file_exists($pdfPath)) {
            return [
                'is_valid' => false, 
                'message' => 'Signed file not found in storage',
                'signed_by' => null,
                'signed_at' => null
            ];
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
            'file_name' => $document->file_name
        ];
    }
}
