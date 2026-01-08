<?php

namespace App\Services;

use App\Models\User;
use App\Models\KycData;
use Illuminate\Http\UploadedFile;

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
        $idPhotoPath = $idPhoto->store("{$email}/kyc/id_card", 'private');
        $selfiePhotoPath = $selfiePhoto->store("{$email}/kyc/selfie", 'private');

        // Update User Status & Data
        $user->update([
            'name' => $data['full_name'],
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
            'id_photo_path' => "private/{$idPhotoPath}",
            'selfie_photo_path' => "private/{$selfiePhotoPath}",
            'status' => 'verified',
        ]);

        // Delete old certificates for this user to ensure only 1 certificate per user
        \App\Models\Certificate::where('user_id', $user->id)->delete();

        // Generate Certificate
        $cert = $this->certificateService->generateUserCertificate($user);

        return [
            'kyc_data' => $kycData,
            'certificate' => $cert,
            'user' => $user,
        ];
    }
}
