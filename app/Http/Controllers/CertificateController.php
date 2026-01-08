<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CertificateController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function issue(Request $request)
    {
        $user = $request->user();

        // Check if user already has an active certificate?
        // For MVP, allow multiple or just new one.
        
        try {
            $cert = $this->certificateService->generateUserCertificate($user);

            return response()->json([
                'message' => 'Certificate issued successfully',
                // Read content if needed for response display, or just ID
                'certificate_content' => file_get_contents($cert->certificate_path),
                'certificate_id' => $cert->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to issue certificate: ' . $e->getMessage()], 500);
        }
    }
}
