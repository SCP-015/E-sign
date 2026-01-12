<?php

namespace App\Services;

use App\Models\User;
use App\Models\KycData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class KycService
{
    protected $certificateService;

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function submitKyc(User $user, array $data, UploadedFile $idPhoto, UploadedFile $selfiePhoto): array
    {
        // Store KYC files in private storage with email-based folder structure
        $email = strtolower($user->email);

        $idExt = strtolower($idPhoto->getClientOriginalExtension() ?: 'bin');
        $selfieExt = strtolower($selfiePhoto->getClientOriginalExtension() ?: 'bin');

        $idRelPath = "{$email}/kyc/id_card/id_" . uniqid() . ".{$idExt}.enc";
        $selfieRelPath = "{$email}/kyc/selfie/selfie_" . uniqid() . ".{$selfieExt}.enc";

        $idPlain = file_get_contents($idPhoto->getRealPath());
        $selfiePlain = file_get_contents($selfiePhoto->getRealPath());

        if ($idPlain === false || $selfiePlain === false) {
            throw new \Exception('Failed to read uploaded KYC files');
        }

        Storage::disk('private')->put($idRelPath, Crypt::encrypt($idPlain));
        Storage::disk('private')->put($selfieRelPath, Crypt::encrypt($selfiePlain));

        // Update User KYC Status (don't overwrite name from auth)
        $user->update([
            'kyc_status' => 'verified',
        ]);

        // Save KYC data to database with private paths
        $kycData = KycData::create([
            'user_id' => $user->id,
            'full_name' => $data['full_name'],
            'id_type' => $data['id_type'],
            'id_number' => $data['id_number'],
            'date_of_birth' => $data['date_of_birth'],
            'address' => $data['address'],
            'city' => $data['city'],
            'province' => $data['province'],
            'postal_code' => $data['postal_code'],
            'id_photo_path' => "private/{$idRelPath}",
            'selfie_photo_path' => "private/{$selfieRelPath}",
            'status' => 'verified',
        ]);

        // Keep history: mark old certificates inactive, then issue a new active certificate
        \App\Models\Certificate::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'inactive']);

        // Generate Certificate
        $cert = $this->certificateService->generateUserCertificate($user);

        return [
            'kyc_data' => $kycData,
            'certificate' => $cert,
            'user' => $user,
        ];
    }

    public function submitKycResult(int $userId, array $validated, UploadedFile $idPhoto, UploadedFile $selfiePhoto): array
    {
        try {
            $user = User::findOrFail($userId);

            $result = $this->submitKyc($user, $validated, $idPhoto, $selfiePhoto);

            $data = [
                'id' => $result['user']->id,
                'full_name' => $validated['full_name'] ?? null,
                'id_type' => $validated['id_type'] ?? null,
                'id_number' => $validated['id_number'] ?? null,
                'date_of_birth' => $validated['date_of_birth'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'province' => $validated['province'] ?? null,
                'postal_code' => $validated['postal_code'] ?? null,
                'id_photo_path' => $result['kyc_data']->id_photo_path,
                'selfie_photo_path' => $result['kyc_data']->selfie_photo_path,
                'kyc_status' => 'verified',
                'certificate' => [
                    'id' => $result['certificate']->id,
                    'certificate_number' => $result['certificate']->certificate_number,
                    'status' => $result['certificate']->status,
                    'issued_at' => $result['certificate']->issued_at,
                    'expires_at' => $result['certificate']->expires_at,
                ],
            ];

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'KYC data submitted successfully',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'KYC submission failed: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }
}
