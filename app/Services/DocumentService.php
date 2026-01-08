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
            'status' => 'pending',
            'x_coord' => 10,
            'y_coord' => 10,
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

        // Import pages
        $pageCount = $pdf->setSourceFile($sourcePdf);
        for ($i = 1; $i <= $pageCount; $i++) {
            $pdf->AddPage();
            $tplIdx = $pdf->importPage($i);
            $pdf->useTemplate($tplIdx);
            
            // Add visual signature on the last page? or where X/Y are?
            // Let's assume on the last page for now or all? Usually last.
             if ($i == $pageCount) {
                $pdf->SetXY($document->x_coord, $document->y_coord);
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Cell(50, 15, 'Digitally Signed by ' . $document->user->name, 1, 0, 'C');
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
