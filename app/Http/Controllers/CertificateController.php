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

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if ($user->kyc_status !== 'verified') {
            return response()->json([
                'message' => 'KYC not verified. Please submit KYC before issuing/renewing certificate.',
            ], 400);
        }

        try {
            // Keep only one active certificate per user (MVP)
            Certificate::where('user_id', $user->id)->delete();

            $cert = $this->certificateService->generateUserCertificate($user);

            return response()->json([
                'message' => 'Certificate issued successfully',
                'certificate' => [
                    'id' => $cert->id,
                    'certificate_number' => $cert->certificate_number,
                    'status' => $cert->status,
                    'issued_at' => $cert->issued_at?->toIso8601String(),
                    'expires_at' => $cert->expires_at?->toIso8601String(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to issue certificate: ' . $e->getMessage()], 500);
        }
    }
}
