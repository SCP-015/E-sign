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

    protected function kycAlreadySubmitted(User $user): bool
    {
        if (($user->kyc_status ?? null) === 'verified') {
            return true;
        }

        if (KycData::where('user_id', $user->id)->exists()) {
            return true;
        }

        // Defensive check: prevent duplicate KYC for the same email (in case of legacy data/import issues)
        if (!empty($user->email)) {
            $email = strtolower(trim((string) $user->email));
            return KycData::whereHas('user', function ($q) use ($email) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
            })->exists();
        }

        return false;
    }

    public function __construct(CertificateService $certificateService)
    {
        $this->certificateService = $certificateService;
    }

    public function submitKyc(User $user, array $data, UploadedFile $idPhoto, UploadedFile $selfiePhoto): array
    {
        if ($this->kycAlreadySubmitted($user)) {
            return [
                'status' => 'error',
                'code' => 409,
                'message' => 'KYC already submitted',
                'data' => null,
            ];
        }

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

    public function submitKycResult(string $userId, array $validated, UploadedFile $idPhoto, UploadedFile $selfiePhoto): array
    {
        try {
            $user = User::findOrFail($userId);

            if ($this->kycAlreadySubmitted($user)) {
                return [
                    'status' => 'error',
                    'code' => 409,
                    'message' => 'KYC already submitted',
                    'data' => null,
                ];
            }

            $result = $this->submitKyc($user, $validated, $idPhoto, $selfiePhoto);

            if (($result['status'] ?? null) === 'error') {
                return $result;
            }

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
                'message' => __('KYC submission failed: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }

    public function getMyKycResult(string $userId): array
    {
        try {
            $kyc = KycData::where('user_id', $userId)->latest()->first();
            if (!$kyc) {
                return [
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'KYC data not found',
                    'data' => null,
                ];
            }

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'kyc' => (new \App\Http\Resources\KycResource($kyc))->resolve(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => __('Failed to fetch KYC data: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }

    public function getMyKycFileResult(string $userId, string $type): array
    {
        try {
            $kyc = KycData::where('user_id', $userId)->latest()->first();
            if (!$kyc) {
                return [
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'KYC data not found',
                    'data' => null,
                ];
            }

            $path = null;
            if ($type === 'id') {
                $path = $kyc->id_photo_path;
            } elseif ($type === 'selfie') {
                $path = $kyc->selfie_photo_path;
            }

            if (!is_string($path) || $path === '') {
                return [
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'KYC file not found',
                    'data' => null,
                ];
            }

            $relativePath = str_replace('private/', '', $path);
            if (!Storage::disk('private')->exists($relativePath)) {
                return [
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'KYC file not found',
                    'data' => null,
                ];
            }

            $ciphertext = Storage::disk('private')->get($relativePath);
            try {
                $plaintext = Crypt::decrypt($ciphertext);
            } catch (\Exception $e) {
                $plaintext = $ciphertext;
            }

            $mimeType = 'application/octet-stream';
            $lower = strtolower($relativePath);
            if (str_contains($lower, '.png.')) {
                $mimeType = 'image/png';
            } elseif (str_contains($lower, '.jpg.') || str_contains($lower, '.jpeg.')) {
                $mimeType = 'image/jpeg';
            }

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'content' => $plaintext,
                    'mimeType' => $mimeType,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => __('Failed to fetch KYC file: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }
}
