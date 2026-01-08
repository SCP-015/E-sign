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
            return ['verified' => false, 'error' => 'Document not signed'];
        }

        $pdfPath = Storage::disk('public')->path($document->signed_path);
        
        if (!file_exists($pdfPath)) {
            return ['verified' => false, 'error' => 'File not found'];
        }

        $content = file_get_contents($pdfPath);

        // Extract ByteRange
        // Pattern: /ByteRange [ 0 123 456 789 ]
        if (!preg_match('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $content, $matches)) {
            return ['verified' => false, 'error' => 'ByteRange not found'];
        }

        $start1 = (int)$matches[1];
        $len1 = (int)$matches[2];
        $start2 = (int)$matches[3];
        $len2 = (int)$matches[4];

        // Extract Contents (Signature)
        // Pattern: /Contents <hex>
        // Use a more specific regex to capture the Contents associated with the specific ByteRange if possible,
        // but typically the last signature is what we verify or the one matching the ByteRange context.
        // TCPDF usually puts them near each other in the Sig Dict.
        // Simplified approach: Find the /Contents that is inside the Signature Dictionary matching this ByteRange?
        // Actually, we can just extract the data based on ByteRange and verify against specific constraints.
        // But verifying requires the signature.
        
        // Let's grab the content defined by ByteRange
        $data = substr($content, $start1, $len1) . substr($content, $start2, $len2);
        
        // The signature is usually in the gap.
        $gapStart = $start1 + $len1;
        $gapLen = $start2 - $gapStart; // $start2 should be equal to $gapStart + $gapLen? No, start2 is absolute position.
        // Gap is between ($start1+$len1) and $start2.
        
        $signatureHex = substr($content, $gapStart, $gapLen);
        
        // Clean up the signature hex (remove <, >, whitespace, /Contents, etc if captured loosely)
        // Actually, the gap contains `/Contents <HE X ST RI NG>`.
        // So we need to parse the HEX string out of the gap.
        if (!preg_match('/<([0-9A-Fa-f\s]+)>/', $signatureHex, $sigMatch)) {
             return ['verified' => false, 'error' => 'Signature data not found in gap'];
        }
        
        $signatureData = hex2bin(preg_replace('/\s+/', '', $sigMatch[1]));
        
        // Save to temporary files
        $tempId = uniqid();
        $dataPath = storage_path("app/secure/verify_data_{$tempId}.bin");
        $sigPath = storage_path("app/secure/verify_sig_{$tempId}.p7b");
        
        file_put_contents($dataPath, $data);
        file_put_contents($sigPath, $signatureData);
        
        $rootCaPath = $this->certificateService->getRootCertPath();
        
        // Verify using OpenSSL
        // -noverify: ignore CA verification? No, we want to verify.
        // -CAfile: use our Root CA.
        // -binary: treat content as binary.
        // -inform DER: signature is DER encoded PKCS7.
        
        // Note: tcpdump/openssl might output "Verification successful" or "Verification failure".
        $command = "openssl cms -verify -binary -inform DER -in {$sigPath} -content {$dataPath} -CAfile {$rootCaPath} -out /dev/null 2>&1";
        
        $output = [];
        $resultCode = 0;
        exec($command, $output, $resultCode);
        
        // Cleanup
        unlink($dataPath);
        unlink($sigPath);
        
        $outputStr = implode("\n", $output);
        
        if ($resultCode === 0 && str_contains($outputStr, 'Verification successful')) {
            return ['verified' => true, 'message' => 'Signature is valid and trusted.'];
        }
        
        return [
            'verified' => false, 
            'error' => 'OpenSSL Evaluation Failed',
            'details' => $outputStr,
            'command' => $command
        ];
    }
}
