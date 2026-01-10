<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signature;
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
     * Upload PDF with metadata (MVP flow)
     */
    public function uploadWithMetadata($file, $user, $title = null)
    {
        $email = strtolower($user->email);
        $path = $file->store("{$email}/documents/original", 'private');
        
        // Get page count using FPDI
        $pageCount = 0;
        try {
            $fullPath = Storage::disk('private')->path($path);
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($fullPath);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to get page count: ' . $e->getMessage());
        }
        
        return Document::create([
            'user_id' => $user->id,
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
