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
