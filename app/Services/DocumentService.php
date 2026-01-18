<?php

namespace App\Services;

use App\Helpers\StoragePathHelper;
use App\Http\Resources\DocumentResource;
use Carbon\Carbon;
use App\Models\Certificate;
use App\Models\Document;
use App\Models\DocumentSigningEvidence;
use App\Models\Signature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class DocumentService
{
    protected function decryptPrivateFileToTempPath(string $privatePathWithOptionalPrefix, string $extension)
    {
        $relativePath = str_replace('private/', '', $privatePathWithOptionalPrefix);
        if (!Storage::disk('private')->exists($relativePath)) {
            return null;
        }

        $ciphertext = Storage::disk('private')->get($relativePath);
        try {
            $plaintext = Crypt::decrypt($ciphertext);
        } catch (\Exception $e) {
            // Backward compatibility: older files may be stored unencrypted.
            $plaintext = $ciphertext;
        }

        $tempPath = sys_get_temp_dir() . '/dec_' . uniqid() . '.' . $extension;
        file_put_contents($tempPath, $plaintext);
        return $tempPath;
    }

    public function upsertSigningEvidence(Document $document, Certificate $certificate, ?string $finalPdfPath = null, $signedAt = null): DocumentSigningEvidence
    {
        $certPem = null;
        $certPath = $certificate->certificate_path;

        if (is_string($certPath) && $certPath !== '' && file_exists($certPath)) {
            $pem = file_get_contents($certPath);
            if ($pem !== false) {
                $certPem = $pem;
            }
        }

        $parsed = null;
        if (is_string($certPem) && $certPem !== '' && function_exists('openssl_x509_parse')) {
            $tmp = @openssl_x509_parse($certPem);
            if (is_array($tmp)) {
                $parsed = $tmp;
            }
        }

        $notBefore = null;
        $notAfter = null;
        if (is_array($parsed)) {
            $from = $parsed['validFrom_time_t'] ?? null;
            $to = $parsed['validTo_time_t'] ?? null;
            if (is_int($from)) {
                $notBefore = Carbon::createFromTimestamp($from);
            }
            if (is_int($to)) {
                $notAfter = Carbon::createFromTimestamp($to);
            }
        }

        // Fallback: use DB issued_at / expires_at if cert parsing failed.
        $notBefore = $notBefore ?? $certificate->issued_at;
        $notAfter = $notAfter ?? $certificate->expires_at;

        $fingerprint = null;
        if (is_string($certPem) && $certPem !== '') {
            if (function_exists('openssl_x509_fingerprint')) {
                $fp = @openssl_x509_fingerprint($certPem, 'sha256');
                $fingerprint = is_string($fp) ? $fp : null;
            } else {
                $fingerprint = hash('sha256', $certPem);
            }
        }

        $serial = null;
        $subject = null;
        $issuer = null;
        if (is_array($parsed)) {
            $serial = $parsed['serialNumberHex'] ?? ($parsed['serialNumber'] ?? null);
            $subjectArr = $parsed['subject'] ?? null;
            $issuerArr = $parsed['issuer'] ?? null;
            $subject = is_array($subjectArr) ? json_encode($subjectArr) : (is_string($subjectArr) ? $subjectArr : null);
            $issuer = is_array($issuerArr) ? json_encode($issuerArr) : (is_string($issuerArr) ? $issuerArr : null);
        }

        $signedAtTs = $signedAt;
        if ($signedAtTs === null) {
            $signedAtTs = $document->completed_at ?? $document->signed_at ?? $document->updated_at ?? now();
        }

        $payload = [
            'certificate_id' => $certificate->id,
            'certificate_number' => $certificate->certificate_number,
            'certificate_fingerprint_sha256' => $fingerprint,
            'certificate_serial' => $serial,
            'certificate_subject' => $subject,
            'certificate_issuer' => $issuer,
            'certificate_not_before' => $notBefore,
            'certificate_not_after' => $notAfter,
            'certificate_pem' => $certPem,
            'signed_at' => $signedAtTs,
        ];

        return DocumentSigningEvidence::updateOrCreate(
            ['document_id' => $document->id],
            $payload
        );
    }

    public function upload($file, $user)
    {
        // Use email-based folder structure: private/{email}/documents/
        $email = strtolower($user->email);
        $path = $file->store("{$email}/documents/original", 'private');
        
        return Document::create([
            'user_id' => $user->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => 'pending',
        ]);
    }

    /**
     * Upload PDF with metadata (MVP flow) - TENANT AWARE
     */
    public function uploadWithMetadata($file, $user, $title = null, $tenantId = null)
    {
        // Ensure storage directory exists
        StoragePathHelper::ensureDirectoryExists($tenantId);
        
        // Generate temp filename
        $filename = StoragePathHelper::generateDocumentFilename(
            $tenantId,
            $user->id,
            $file->getClientOriginalName()
        );
        
        // Store file
        $storagePath = StoragePathHelper::getDocumentPath($tenantId, 'original');
        $path = $file->storeAs($storagePath, $filename);
        
        // Get page count using FPDI
        $pageCount = 0;
        try {
            $fullPath = Storage::path($path);
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($fullPath);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to get page count: ' . $e->getMessage());
        }
        
        $document = Document::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'title' => $title ?? $file->getClientOriginalName(),
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'file_type' => 'pdf',
            'file_size_bytes' => $file->getSize(),
            'page_count' => $pageCount,
            'status' => 'pending',
        ]);
        
        // Rename file dengan document ID untuk tenant mode
        if ($tenantId !== null) {
            $finalFilename = "{$document->id}_original.pdf";
            $finalPath = StoragePathHelper::getFullPath($tenantId, 'original', $finalFilename);
            Storage::move($path, $finalPath);
            $document->update(['file_path' => $finalPath]);
        }
        
        return $document;
    }

    public function indexResult(int $userId, ?string $tenantId = null): array
    {
        try {
            // STRICT ISOLATION: filter by tenant context
            $documents = Document::with(['signers', 'tenant'])
                ->accessibleByUser($userId, $tenantId)
                ->latest()
                ->get();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => DocumentResource::collection($documents)->resolve(),
                'context' => [
                    'mode' => $tenantId ? 'tenant' : 'personal',
                    'tenant_id' => $tenantId,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Failed to fetch documents: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function uploadWithMetadataResult(int $userId, UploadedFile $file, ?string $title = null, ?string $tenantId = null): array
    {
        try {
            $cert = Certificate::where('user_id', $userId)->where('status', 'active')->first();
            if (!$cert) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'No active certificate found. Please complete KYC verification first.',
                    'data' => null,
                ];
            }

            $user = \App\Models\User::findOrFail($userId);
            
            // Validate tenant membership jika tenant mode
            if ($tenantId && !$user->tenants()->where('tenants.id', $tenantId)->exists()) {
                return [
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'You are not a member of this organization',
                    'data' => null,
                ];
            }
            
            $doc = $this->uploadWithMetadata($file, $user, $title ?? $file->getClientOriginalName(), $tenantId);

            return [
                'status' => 'success',
                'code' => 201,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'documentId' => $doc->id,
                    'fileName' => basename($doc->file_path),
                    'fileType' => $doc->file_type,
                    'fileSizeBytes' => $doc->file_size_bytes,
                    'pageCount' => $doc->page_count,
                    'status' => $doc->status,
                    'tenantId' => $doc->tenant_id,
                    'mode' => $doc->isPersonal() ? 'personal' : 'tenant',
                    'createdAt' => $doc->created_at->toIso8601String(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Upload failed: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function showResult(int $documentId, int $userId, ?string $tenantId = null): array
    {
        try {
            $document = Document::with(['signers', 'tenant'])
                ->where('id', $documentId)
                ->forCurrentContext($tenantId)
                ->where(function($query) use ($userId) {
                    $query->where('user_id', $userId)
                          ->orWhereHas('signers', function($q) use ($userId) {
                              $q->where('user_id', $userId);
                          });
                })
                ->firstOrFail();

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => (new \App\Http\Resources\DocumentResource($document))->resolve(),
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

    /**
     * Finalize PDF with all placements and QR code (MVP flow)
     */
    public function finalizePdf(Document $document, $verifyToken, array $qrConfig)
    {
        $relativePath = str_replace('private/', '', $document->file_path);
        $docPath = Storage::disk('private')->path($relativePath);
        
        // Normalize with Ghostscript
        $tempCleanPath = sys_get_temp_dir() . '/clean_' . uniqid() . '.pdf';
        $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile={$tempCleanPath} " . escapeshellarg($docPath);
        exec($cmd, $output, $returnVar);
        $sourcePdf = ($returnVar === 0 && file_exists($tempCleanPath)) ? $tempCleanPath : $docPath;

        // Initialize FPDI
        $pdf = new Fpdi();
        // Avoid TCPDF auto page breaks adding an extra blank page when drawing near the bottom
        $pdf->SetAutoPageBreak(false, 0);

        // Embed verify marker into PDF metadata so /verify/upload can map back without decoding QR image
        $verifyUrl = url('/api/verify/' . $verifyToken);
        $pdf->SetTitle($document->title ?? ('Document #' . $document->id));
        $pdf->SetAuthor($document->user?->name ?? 'E-Sign');
        $pdf->SetSubject('E-Sign Document');
        $pdf->SetKeywords('E-SIGN;VERIFY_TOKEN=' . $verifyToken . ';VERIFY_URL=' . $verifyUrl);

        // Best-effort: apply a real PDF digital signature using owner's certificate if available
        try {
            $cert = $document->user?->certificate;
            $certPath = $cert?->certificate_path;
            $keyPath = $cert?->private_key_path;

            if ($certPath && $keyPath && file_exists($certPath) && file_exists($keyPath)) {
                $certificateContent = file_get_contents($certPath);
                $privateKeyContent = file_get_contents($keyPath);

                if ($certificateContent !== false && $privateKeyContent !== false) {
                    $info = [
                        'Name' => $document->user?->name,
                        'Location' => 'Office',
                        'Reason' => 'Digital Signature',
                        'ContactInfo' => $document->user?->email,
                    ];
                    $pdf->setSignature($certificateContent, $privateKeyContent, '', '', 2, $info);
                }
            }
        } catch (\Exception $e) {
            // Ignore: final PDF can still be generated with visual signatures and QR.
        }
        
        // Load all placements with relationships
        $placements = $document->placements()->with(['signature', 'signer'])->get();

        $tempFiles = [];
        
        // Import all pages
        $pageCount = $pdf->setSourceFile($sourcePdf);
        $lastPageWidth = 0;
        $lastPageHeight = 0;
        
        for ($i = 1; $i <= $pageCount; $i++) {
            $tplIdx = $pdf->importPage($i);
            $pageSize = $pdf->getTemplateSize($tplIdx);
            $pageWidth = $pageSize['width'];
            $pageHeight = $pageSize['height'];
            
            $lastPageWidth = $pageWidth;
            $lastPageHeight = $pageHeight;
            
            $orientation = $pageHeight > $pageWidth ? 'P' : 'L';
            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            $pdf->useTemplate($tplIdx);
            
            // Add all signature placements for this page
            $pagePlacements = $placements->where('page', $i);
            foreach ($pagePlacements as $placement) {
                $imageType = strtoupper($placement->signature->image_type);
                $ext = ($imageType === 'SVG') ? 'svg' : 'png';
                $sigImagePath = $this->decryptPrivateFileToTempPath($placement->signature->image_path, $ext);
                if ($sigImagePath && file_exists($sigImagePath)) {
                    $tempFiles[] = $sigImagePath;
                    $x = $placement->x;
                    $y = $placement->y;
                    $w = $placement->w;
                    $h = $placement->h;

                    // Support normalized coordinates (0..1) from frontend
                    if ($x <= 1 && $y <= 1 && $w <= 1 && $h <= 1) {
                        $x = $x * $pageWidth;
                        $y = $y * $pageHeight;
                        $w = $w * $pageWidth;
                        $h = $h * $pageHeight;
                    }

                    if ($imageType === 'SVG') {
                        $pdf->ImageSVG($sigImagePath, $x, $y, $w, $h);
                    } else {
                        $pdf->Image($sigImagePath, $x, $y, $w, $h, 'PNG');
                    }
                }
            }
        }
        
        // Add QR code on last page
        $qrPage = $qrConfig['page'] === 'LAST' ? $pageCount : (int) $qrConfig['page'];
        $qrSize = $qrConfig['size'] ?? 35;
        $marginBottom = $qrConfig['marginBottom'] ?? 15;
        $marginRight = $qrConfig['marginRight'] ?? 15;

        // Ensure we're writing on the intended page (important when TCPDF might have advanced state)
        $pdf->setPage($qrPage);
        
        // Position at bottom-right corner (small)
        $qrX = $lastPageWidth - $qrSize - $marginRight;
        $qrY = $lastPageHeight - $qrSize - $marginBottom;
        
        // Verify URL
        // NOTE: $verifyUrl is defined above (also embedded in metadata)
        
        $pdf->write2DBarcode(
            $verifyUrl,
            'QRCODE,H',
            $qrX,
            $qrY,
            $qrSize,
            $qrSize,
            array(
                'border' => false,
                'vpadding' => 0,
                'hpadding' => 0,
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => array(255, 255, 255),
            ),
            'N'
        );

        // Also write a tiny (near-invisible) text marker so token is present even if metadata is stripped
        try {
            $pdf->SetFont('helvetica', '', 4);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Text(1, $lastPageHeight - 2, 'VERIFY_TOKEN=' . $verifyToken);
        } catch (\Exception $e) {
            // ignore
        }
        
        // Save final PDF
        $finalFileName = 'final_' . basename($document->file_path);
        $email = strtolower($document->user->email);
        $finalPath = "private/{$email}/documents/final/{$finalFileName}";
        
        $finalDir = storage_path("app/private/{$email}/documents/final");
        if (!is_dir($finalDir)) {
            mkdir($finalDir, 0755, true);
        }
        
        $fullFinalPath = storage_path("app/{$finalPath}");
        $pdf->Output($fullFinalPath, 'F');
        
        // Clean up
        if (file_exists($tempCleanPath)) {
            unlink($tempCleanPath);
        }

        foreach ($tempFiles as $tf) {
            if ($tf && file_exists($tf)) {
                unlink($tf);
            }
        }
        
        return $finalPath;
    }

    /**
     * Sign PDF with signature image + QR code
     * 
     * @param Document $document
     * @param $certificate User's certificate
     * @param Signature $signature User's signature image
     * @param array $signaturePosition Position for signature {x, y, width, height, page}
     * @return string Signed document path
     */
    public function signPdf(Document $document, $certificate, Signature $signature, array $signaturePosition)
    {
        // Remove 'private/' prefix if present since Storage::disk('private')->path() already adds it
        $relativePath = str_replace('private/', '', $document->file_path);
        $docPath = Storage::disk('private')->path($relativePath);
        
        // Ensure Certificate and Key paths
        $certPath = $certificate->certificate_path;
        $keyPath = $certificate->private_key_path;
        
        if (!file_exists($certPath) || !file_exists($keyPath)) {
            throw new \Exception("Certificate files missing.");
        }

        // Get signature image path
        $sigExt = strtolower($signature->image_type) === 'svg' ? 'svg' : 'png';
        $sigImagePath = $this->decryptPrivateFileToTempPath($signature->image_path, $sigExt);

        if (!$sigImagePath || !file_exists($sigImagePath)) {
            throw new \Exception("Signature image not found.");
        }

        // --- GHOSTSCRIPT NORMALIZATION ---
        $tempCleanPath = sys_get_temp_dir() . '/clean_' . uniqid() . '.pdf';
        $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile={$tempCleanPath} " . escapeshellarg($docPath);
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($tempCleanPath)) {
            \Illuminate\Support\Facades\Log::error("GS Failed: " . implode("\n", $output));
            $sourcePdf = $docPath;
        } else {
            $sourcePdf = $tempCleanPath;
        }

        // Initialize FPDI
        $pdf = new Fpdi();
        // Avoid TCPDF auto page breaks adding an extra blank page when drawing near the bottom
        $pdf->SetAutoPageBreak(false, 0);

        // Set certificate for digital signature
        $certificateContent = file_get_contents($certPath);
        $privateKeyContent = file_get_contents($keyPath);
        
        $info = array(
            'Name' => $document->user->name,
            'Location' => 'Office',
            'Reason' => 'Digital Signature',
            'ContactInfo' => $document->user->email,
        );
        $pdf->setSignature($certificateContent, $privateKeyContent, '', '', 2, $info);

        // Generate QR Code data
        $qrData = json_encode([
            'document_id' => $document->id,
            'signed_by' => $document->user->name,
            'signed_at' => now()->toIso8601String(),
            'certificate_id' => $certificate->id,
            'verify_url' => url("/verify/{$document->id}"),
        ]);

        // Import pages - preserve original page size
        $pageCount = $pdf->setSourceFile($sourcePdf);
        $lastPageWidth = 0;
        $lastPageHeight = 0;
        
        for ($i = 1; $i <= $pageCount; $i++) {
            // Import page first to get its dimensions
            $tplIdx = $pdf->importPage($i);
            
            // Get the dimensions of the imported page
            $pageSize = $pdf->getTemplateSize($tplIdx);
            $pageWidth = $pageSize['width'];
            $pageHeight = $pageSize['height'];
            
            // Save last page dimensions for QR placement
            $lastPageWidth = $pageWidth;
            $lastPageHeight = $pageHeight;
            
            // Add page with original dimensions
            $orientation = $pageHeight > $pageWidth ? 'P' : 'L';
            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            
            // Use the imported template
            $pdf->useTemplate($tplIdx);
            
            // Add signature image on specified page
            if ($i == $signaturePosition['page']) {
                $sigX = $pageWidth * $signaturePosition['x'];
                $sigY = $pageHeight * $signaturePosition['y'];
                $sigWidth = $pageWidth * $signaturePosition['width'];
                $sigHeight = $pageHeight * $signaturePosition['height'];
                
                // Determine image type for TCPDF
                $imageType = strtoupper($signature->image_type);
                if ($imageType === 'SVG') {
                    // For SVG, use ImageSVG method
                    $pdf->ImageSVG($sigImagePath, $sigX, $sigY, $sigWidth, $sigHeight);
                } else {
                    // For PNG, use Image method
                    $pdf->Image($sigImagePath, $sigX, $sigY, $sigWidth, $sigHeight, 'PNG');
                }
            }
            
            // Add QR code on LAST PAGE at bottom-right (default position)
            if ($i == $pageCount) {
                // QR position: bottom-right corner with margin
                $qrSize = min($pageWidth, $pageHeight) * 0.12; // 12% of smaller dimension
                $qrMargin = 10; // 10mm margin from edge
                $qrX = $pageWidth - $qrSize - $qrMargin;
                $qrY = $pageHeight - $qrSize - $qrMargin;
                
                // Use TCPDF's native QR code generator
                $pdf->write2DBarcode(
                    $qrData,
                    'QRCODE,H',
                    $qrX,
                    $qrY,
                    $qrSize,
                    $qrSize,
                    array(
                        'border' => false,
                        'vpadding' => 0,
                        'hpadding' => 0,
                        'fgcolor' => array(0, 0, 0),
                        'bgcolor' => array(255, 255, 255),
                    ),
                    'N'
                );
            }
        }

        // Output to private storage
        $signedFileName = 'signed_' . basename($document->file_path);
        $email = strtolower($document->user->email);
        $signedPath = "private/{$email}/documents/signed/{$signedFileName}";
        
        $signedDir = storage_path("app/private/{$email}/documents/signed");
        if (!is_dir($signedDir)) {
            mkdir($signedDir, 0755, true);
        }
        
        $fullSignedPath = storage_path("app/{$signedPath}");
        $pdf->Output($fullSignedPath, 'F');

        // Update Document
        $document->update([
            'signed_path' => $signedPath,
            'status' => 'signed'
        ]);

        // Clean up temp file
        if (file_exists($tempCleanPath)) {
            unlink($tempCleanPath);
        }

        if ($sigImagePath && file_exists($sigImagePath)) {
            unlink($sigImagePath);
        }

        return $signedPath;
    }
}
