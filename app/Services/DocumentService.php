<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class DocumentService
{
    public function upload($file, $user)
    {
        // Use email-based folder structure: private/{email}/documents/
        $email = strtolower($user->email);
        $path = $file->store("{$email}/documents", 'private');
        
        return Document::create([
            'user_id' => $user->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'status' => 'pending',
            // Legacy absolute coordinates (deprecated)
            'x_coord' => 10,
            'y_coord' => 10,
            // Default QR position (center of page)
            'signature_x' => 0.5,
            'signature_y' => 0.5,
            'signature_width' => 0.15,
            'signature_height' => 0.15,
            'signature_page' => 1,
        ]);
    }

    public function signPdf(Document $document, $certificate)
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

        // --- GHOSTSCRIPT NORMALIZATION ---
        // FPDI fails on encrypted/newer PDFs. We use GS to rewrite it to PDF 1.4
        $tempCleanPath = sys_get_temp_dir() . '/clean_' . uniqid() . '.pdf';
        
        // Command to linearize and strip locks/encryption by rewriting to a fresh PDF
        // -dCompatibilityLevel=1.4 is best for FPDI
        $cmd = "gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile={$tempCleanPath} " . escapeshellarg($docPath);
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0 || !file_exists($tempCleanPath)) {
            \Illuminate\Support\Facades\Log::error("GS Failed: " . implode("\n", $output));
            // Fallback to original if GS fails (though unlikely if installed)
            $sourcePdf = $docPath;
        } else {
            $sourcePdf = $tempCleanPath;
        }

        // Initialize FPDI
        $pdf = new Fpdi();

        // set certificate file
        $certificateContent = file_get_contents($certPath);
        $privateKeyContent = file_get_contents($keyPath);
        
        // TCPDF setSignature expects full content or file:// paths.
        // It requires the certificate and private key.
        // info
        $info = array(
            'Name' => $document->user->name,
            'Location' => 'Office',
            'Reason' => 'Digital Signature',
            'ContactInfo' => $document->user->email,
        );

        // set document signature
        $pdf->setSignature($certificateContent, $privateKeyContent, '', '', 2, $info);

        // Generate QR Code data
        $qrData = json_encode([
            'document_id' => $document->id,
            'signed_by' => $document->user->name,
            'signed_at' => now()->toIso8601String(),
            'certificate_id' => $certificate->id,
        ]);
        
        // Get QR position from document (relative coordinates 0-1)
        $qrPos = $document->qr_position ?? [
            'x' => 0.5,
            'y' => 0.5,
            'width' => 0.15,
            'height' => 0.15,
            'page' => 1,
        ];
        
        // Import pages - preserve original page size
        $pageCount = $pdf->setSourceFile($sourcePdf);
        for ($i = 1; $i <= $pageCount; $i++) {
            // Import page first to get its dimensions
            $tplIdx = $pdf->importPage($i);
            
            // Get the dimensions of the imported page
            $pageSize = $pdf->getTemplateSize($tplIdx);
            $pageWidth = $pageSize['width'];
            $pageHeight = $pageSize['height'];
            
            // Add page with original dimensions (not forced A4)
            // Detect orientation: if height > width = portrait, else = landscape
            $orientation = $pageHeight > $pageWidth ? 'P' : 'L';
            $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
            
            // Use the imported template
            $pdf->useTemplate($tplIdx);
            
            // Add QR code on specified page using TCPDF's built-in write2DBarcode (no extensions needed!)
            if ($i == $qrPos['page']) {
                // Get page dimensions
                $pageWidth = $pdf->getPageWidth();
                $pageHeight = $pdf->getPageHeight();
                
                // Convert relative coordinates to absolute pixels
                $actualX = $pageWidth * $qrPos['x'];
                $actualY = $pageHeight * $qrPos['y'];
                $actualWidth = $pageWidth * $qrPos['width'];
                $actualHeight = $pageHeight * $qrPos['height'];
                
                // Use TCPDF's native QR code generator (built-in, no dependencies)
                $pdf->write2DBarcode(
                    $qrData,           // QR code content
                    'QRCODE,H',        // Type: QR Code with High error correction
                    $actualX,          // X position
                    $actualY,          // Y position
                    $actualWidth,      // Width
                    $actualHeight,     // Height
                    array(             // Style
                        'border' => false,
                        'vpadding' => 0,
                        'hpadding' => 0,
                        'fgcolor' => array(0, 0, 0),
                        'bgcolor' => false,
                    ),
                    'N'                // Alignment
                );
            }
        }

        // Output to private storage with email-based folder structure
        $signedFileName = 'signed_' . basename($document->file_path);
        $email = strtolower($document->user->email);
        $signedPath = "private/{$email}/documents/{$signedFileName}";
        
        // Create directory if not exists
        $signedDir = storage_path("app/private/{$email}/documents");
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

        return $signedPath;
    }
}
