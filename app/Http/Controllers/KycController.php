<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CertificateService;
use App\Models\User;

class KycController extends Controller
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function submit(Request $request)
    {
        // Simulate Mobile App sending FormData
        $request->validate([
            'nik' => 'required|string',
            'name' => 'required|string', // Name from ID Card
            'id_card' => 'required|file|mimes:jpg,jpeg,png',
            'selfie' => 'required|file|mimes:jpg,jpeg,png',
            // 'email' => 'required|email', // Removed: use Auth user
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!$user) {
             return response()->json(['message' => 'Unauthorized'], 401);
        }

        // 1. Simulate Verification (Always True for MVP)
        // In real app, we would verify OCR and Face Match here.
        
        // 2. Store Files (Optional for MVP, but good practice)
        $idCardPath = $request->file('id_card')->store('kyc/id_cards', 'local'); // Secure storage
        $selfiePath = $request->file('selfie')->store('kyc/selfies', 'local');

        // 3. Update User Status & Data
        $user->update([
            'name' => $request->name, // Update name to match ID Card
            'kyc_status' => 'verified',
            // 'nik' => $request->nik // Add NIK column if needed, skipping for MVP schema simplicity
        ]);

        // 4. Generate Certificate
        // We reuse the logic from CertificateController logic but internally
        // Note: modify generateUserCertificate to be public or accessible
        // Creating a fresh Request or passing user directly?
        // CertificateService->generateUserCertificate logic handles creation.
        
        try {
            $cert = $this->certificateService->generateUserCertificate($user);
            return response()->json([
                'message' => 'KYC Verified & Certificate Issued',
                'certificate_id' => $cert->id
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Certificate Generation Failed: ' . $e->getMessage()], 500);
        }
    }
}
