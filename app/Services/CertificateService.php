<?php

namespace App\Services;

use App\Models\KycData;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    protected $caKeyPath;
    protected $caCertPath;
    protected $securePath;

    protected TenantDatabaseManager $dbManager;
    protected RootCAService $rootCAService;

    public function __construct(TenantDatabaseManager $dbManager, RootCAService $rootCAService)
    {
        $this->dbManager = $dbManager;
        $this->rootCAService = $rootCAService;

        // Use central secure path for rootCA ONLY
        $this->securePath = storage_path('app/private/rootca/');
        $this->caKeyPath = $this->securePath . 'rootCA.key';
        $this->caCertPath = $this->securePath . 'rootCA.crt';
        
        if (!file_exists($this->securePath)) {
            mkdir($this->securePath, 0755, true);
        }
    }

    public function ensureRootCA()
    {
        if (file_exists($this->caKeyPath) && file_exists($this->caCertPath)) {
            return;
        }

        // Generate Root Key
        exec("openssl genrsa -out {$this->caKeyPath} 2048");

        // Generate Root Certificate (Self-Signed)
        $subj = "/C=US/ST=State/L=City/O=MyOrg/OU=OrgUnit/CN=MyRootCA";
        exec("openssl req -x509 -new -nodes -key {$this->caKeyPath} -sha256 -days 3650 -out {$this->caCertPath} -subj \"{$subj}\"");
    }

    public function signCsr(string $csrContent, string $subjectAltName = null)
    {
        $this->ensureRootCA();

        $csrId = Str::random(16);
        // User certs/keys can still be in the central secure for now, or tenant specific?
        // User said "Root CA is central... anyone can create certificate based on 1 Root CA".
        // Use central path for simplicity for now as requested "root ca di central".
        $csrPath = $this->securePath . "{$csrId}.csr";
        $certPath = $this->securePath . "{$csrId}.crt";

        file_put_contents($csrPath, $csrContent);
        
        // Configuration for extensions if needed, but for MVP keep simple.
        // Sign the CSR
        $command = "openssl x509 -req -in {$csrPath} -CA {$this->caCertPath} -CAkey {$this->caKeyPath} -CAcreateserial -out {$certPath} -days 365 -sha256";
        exec($command);

        if (file_exists($certPath)) {
            $certContent = file_get_contents($certPath);
            // Cleanup
            unlink($csrPath);
            // unlink($certPath); // Keep or return path? Plan says store path in DB.
            return ['content' => $certContent, 'path' => $certPath];
        }

        return null;
    }

    public function generateUserCertificate($user)
    {
        $this->ensureRootCA();

        // Use email-based folder structure: private/{email}/certificate/
        $email = strtolower($user->email);
        $userCertDir = storage_path("app/private/{$email}/certificate/");
        
        // Create directory if not exists
        if (!is_dir($userCertDir)) {
            mkdir($userCertDir, 0755, true);
        }

        $certFileName = str_replace(['@', '.'], '_', $email);
        $keyPath = $userCertDir . "{$certFileName}.key";
        $csrPath = $userCertDir . "{$certFileName}.csr";
        $certPath = $userCertDir . "{$certFileName}.crt";

        // 1. Generate Private Key
        exec("openssl genrsa -out {$keyPath} 2048");

        // 2. Generate CSR
        $subj = "/C=US/ST=State/L=City/O=MyOrg/OU=Users/CN={$user->name}/emailAddress={$user->email}";
        $subj = escapeshellarg($subj);
        exec("openssl req -new -key {$keyPath} -out {$csrPath} -subj {$subj}");

        // 3. Sign CSR with central rootCA
        $command = "openssl x509 -req -in {$csrPath} -CA {$this->caCertPath} -CAkey {$this->caKeyPath} -CAcreateserial -out {$certPath} -days 365 -sha256";
        exec($command);

        if (file_exists($certPath) && file_exists($keyPath)) {
            // Generate certificate number (format: CERT-YYYY-XXXXX)
            $certNumber = 'CERT-' . date('Y') . '-' . str_pad($user->id, 5, '0', STR_PAD_LEFT);
            
            // Create Database Record
            return \App\Models\Certificate::create([
                'user_id' => $user->id,
                'certificate_number' => $certNumber,
                'private_key_path' => $keyPath,
                'public_key_path' => $csrPath,
                'certificate_path' => $certPath,
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addYear(), // Valid for 1 year
            ]);
        }

        throw new \Exception("Failed to generate certificate files.");
    }

    public function ensureTenantUserCertificate(User $user, string $tenantId): \App\Models\Certificate
    {
        $tenant = Tenant::findOrFail($tenantId);

        // Ensure tenant Root CA exists first (RootCAService handles its own DB switching)
        $rootCa = $this->rootCAService->getTenantRootCA($tenantId);
        if (!$rootCa) {
            $rootCa = $this->rootCAService->generateTenantRootCA($tenant);
        }

        $this->dbManager->switchToTenantDatabase($tenantId);

        try {
            $existing = \App\Models\Certificate::on('tenant')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('issued_at')
                ->orderByDesc('created_at')
                ->first();

            if ($existing) {
                return $existing;
            }

            $userId = (string) $user->id;
            $storagePath = storage_path("app/private/tenants/{$tenantId}/certificates/{$userId}/");
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            $keyPath = $storagePath . 'user.key';
            $csrPath = $storagePath . 'user.csr';
            $certPath = $storagePath . 'user.crt';

            $userName = $user->name ?: $user->email;
            $orgName = $tenant->company_legal_name ?? $tenant->name;
            $subj = "/C=" . ($tenant->company_country ?? 'ID') . "/O={$orgName}/OU=Users/CN={$userName}/emailAddress={$user->email}";
            $subj = escapeshellarg($subj);

            exec("openssl genrsa -out {$keyPath} 2048");
            exec("openssl req -new -key {$keyPath} -out {$csrPath} -subj {$subj}");

            $caCertPath = $rootCa->certificate_path;
            $caKeyPath = $rootCa->private_key_path;

            $command = "openssl x509 -req -in {$csrPath} -CA {$caCertPath} -CAkey {$caKeyPath} -CAcreateserial -out {$certPath} -days 365 -sha256";
            exec($command);

            if (!file_exists($certPath) || !file_exists($keyPath)) {
                throw new \Exception('Failed to generate tenant user certificate files.');
            }

            \App\Models\Certificate::on('tenant')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            $certNumber = 'TENANT-CERT-' . date('Y') . '-' . Str::upper(Str::random(10));

            return \App\Models\Certificate::on('tenant')->create([
                'user_id' => $user->id,
                'root_ca_id' => $rootCa->id,
                'certificate_number' => $certNumber,
                'private_key_path' => $keyPath,
                'public_key_path' => $csrPath,
                'certificate_path' => $certPath,
                'status' => 'active',
                'issued_at' => now(),
                'expires_at' => now()->addYear(),
            ]);
        } finally {
            $this->dbManager->switchToCentralDatabase();
        }
    }
    
    public function issueCertificateResult(string $userId): array
    {
        try {
            $user = \App\Models\User::findOrFail($userId);

            if ($user->kyc_status !== 'verified') {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'KYC not verified. Please submit KYC before issuing/renewing certificate.',
                    'data' => null,
                ];
            }

            $kyc = KycData::where('user_id', $user->id)->latest()->first();
            if (!$kyc || ($kyc->status ?? null) !== 'verified') {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'KYC data is missing or not verified. Please submit KYC before issuing/renewing certificate.',
                    'data' => null,
                ];
            }

            $idPhotoPath = is_string($kyc->id_photo_path) ? $kyc->id_photo_path : null;
            $selfiePhotoPath = is_string($kyc->selfie_photo_path) ? $kyc->selfie_photo_path : null;
            if (!$idPhotoPath || !$selfiePhotoPath) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'KYC documents are missing. Please re-submit KYC before issuing/renewing certificate.',
                    'data' => null,
                ];
            }

            $idRel = str_replace('private/', '', $idPhotoPath);
            $selfieRel = str_replace('private/', '', $selfiePhotoPath);
            if (!Storage::disk('private')->exists($idRel) || !Storage::disk('private')->exists($selfieRel)) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'KYC documents are missing in storage. Please re-submit KYC before issuing/renewing certificate.',
                    'data' => null,
                ];
            }

            \App\Models\Certificate::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            $cert = $this->generateUserCertificate($user);

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'Certificate issued successfully',
                'data' => [
                    'certificate' => [
                        'id' => $cert->id,
                        'certificate_number' => $cert->certificate_number,
                        'status' => $cert->status,
                        'issued_at' => $cert->issued_at?->toIso8601String(),
                        'expires_at' => $cert->expires_at?->toIso8601String(),
                    ],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => __('Failed to issue certificate: :error', ['error' => $e->getMessage()]),
                'data' => null,
            ];
        }
    }

    public function getRootCertPath()
    {
        return $this->caCertPath;
    }
}
