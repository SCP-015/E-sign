<?php

namespace App\Http\Controllers;

use App\Http\Requests\KycSubmitRequest;
use App\Http\Resources\KycResource;
use App\Services\KycService;
use App\Models\User;

class KycController extends Controller
{
    protected $kycService;

    public function __construct(KycService $kycService)
    {
        $this->kycService = $kycService;
    }

    public function submit(KycSubmitRequest $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $result = $this->kycService->submitKyc(
                $user,
                $request->validated(),
                $request->file('id_photo'),
                $request->file('selfie_photo')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'KYC data submitted successfully',
                'data' => [
                    'id' => $result['user']->id,
                    'full_name' => $request->full_name,
                    'id_type' => $request->id_type,
                    'id_number' => $request->id_number,
                    'date_of_birth' => $request->date_of_birth,
                    'address' => $request->address,
                    'city' => $request->city,
                    'province' => $request->province,
                    'postal_code' => $request->postal_code,
                    'id_photo_path' => $result['kyc_data']->id_photo_path,
                    'selfie_photo_path' => $result['kyc_data']->selfie_photo_path,
                    'kyc_status' => 'verified',
                    'certificate' => [
                        'id' => $result['certificate']->id,
                        'certificate_number' => $result['certificate']->certificate_number,
                        'status' => $result['certificate']->status,
                        'issued_at' => $result['certificate']->issued_at,
                        'expires_at' => $result['certificate']->expires_at,
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'KYC submission failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
