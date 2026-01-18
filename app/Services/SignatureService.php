<?php

namespace App\Services;

use App\Models\Signature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class SignatureService
{
    public function index(int $userId, ?string $tenantId = null): array
    {
        // STRICT ISOLATION: filter by tenant context
        $signatures = Signature::availableForContext($userId, $tenantId)
            ->with('tenant')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($signature) {
                return [
                    'id' => $signature->id,
                    'name' => $signature->name,
                    'image_type' => $signature->image_type,
                    'is_default' => $signature->is_default,
                    'is_portable' => $signature->isPortable(),
                    'tenant_id' => $signature->tenant_id,
                    'created_at' => $signature->created_at,
                ];
            });

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => $signatures,
            'context' => [
                'mode' => $tenantId ? 'tenant' : 'personal',
                'tenant_id' => $tenantId,
                'portable_count' => $signatures->where('is_portable', true)->count(),
            ],
        ];
    }

    public function store(int $userId, string $userEmail, UploadedFile $image, ?string $name, bool $isDefault, ?string $tenantId = null): array
    {
        $extension = strtolower($image->getClientOriginalExtension());
        $imageType = $extension === 'svg' ? 'svg' : 'png';

        $email = strtolower($userEmail);
        $filename = 'signature_' . uniqid() . '.' . $extension . '.enc';
        $path = "{$email}/signatures/{$filename}";

        $plaintext = file_get_contents($image->getRealPath());
        if ($plaintext === false) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Failed to read uploaded signature file',
                'data' => null,
            ];
        }

        Storage::disk('private')->put($path, Crypt::encrypt($plaintext));

        if ($isDefault) {
            Signature::where('user_id', $userId)->update(['is_default' => false]);
        }

        $signature = Signature::create([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'name' => $name ?: 'My Signature',
            'image_path' => "private/{$path}",
            'image_type' => $imageType,
            'is_default' => $isDefault,
        ]);

        return [
            'status' => 'success',
            'code' => 201,
            'message' => 'Signature uploaded successfully',
            'data' => [
                'signature' => [
                    'id' => $signature->id,
                    'name' => $signature->name,
                    'image_type' => $signature->image_type,
                    'is_default' => $signature->is_default,
                    'is_portable' => $signature->isPortable(),
                    'tenant_id' => $signature->tenant_id,
                    'mode' => $signature->isPersonal() ? 'personal' : 'tenant',
                    'created_at' => $signature->created_at,
                ],
            ],
        ];
    }

    public function getImage(int $userId, int $signatureId): array
    {
        $signature = Signature::where('id', $signatureId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $relativePath = str_replace('private/', '', $signature->image_path);
        if (!Storage::disk('private')->exists($relativePath)) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Signature image not found',
                'data' => null,
            ];
        }

        $ciphertext = Storage::disk('private')->get($relativePath);
        try {
            $plaintext = Crypt::decrypt($ciphertext);
        } catch (\Exception $e) {
            $plaintext = $ciphertext;
        }

        $mimeType = $signature->image_type === 'svg' ? 'image/svg+xml' : 'image/png';

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'OK',
            'data' => [
                'content' => $plaintext,
                'mimeType' => $mimeType,
            ],
        ];
    }

    public function setDefault(int $userId, int $signatureId): array
    {
        $signature = Signature::where('id', $signatureId)
            ->where('user_id', $userId)
            ->firstOrFail();

        Signature::where('user_id', $userId)->update(['is_default' => false]);
        $signature->update(['is_default' => true]);

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Signature set as default',
            'data' => [
                'signature' => [
                    'id' => $signature->id,
                    'name' => $signature->name,
                    'is_default' => true,
                ],
            ],
        ];
    }

    public function destroy(int $userId, int $signatureId): array
    {
        $signature = Signature::where('id', $signatureId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $relativePath = str_replace('private/', '', $signature->image_path);
        if (Storage::disk('private')->exists($relativePath)) {
            Storage::disk('private')->delete($relativePath);
        }

        $signature->delete();

        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Signature deleted successfully',
            'data' => null,
        ];
    }
}
