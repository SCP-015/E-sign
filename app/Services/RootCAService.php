<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\RootCertificateAuthority;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class RootCAService
{
    protected TenantDatabaseManager $dbManager;

    public function __construct(TenantDatabaseManager $dbManager)
    {
        $this->dbManager = $dbManager;
    }

    /**
     * Generate Root CA untuk tenant baru.
     *
     * @param Tenant $tenant
     * @return RootCertificateAuthority
     * @throws Exception
     */
    public function generateTenantRootCA(Tenant $tenant): RootCertificateAuthority
    {
        try {
            // Switch to tenant database
            $this->dbManager->switchToTenantDatabase($tenant->id);

            // Check if Root CA already exists
            $existingCA = RootCertificateAuthority::on('tenant')->where('status', 'active')->first();
            if ($existingCA) {
                Log::warning("Root CA already exists for tenant: {$tenant->id}");
                
                // Switch back to central
                $this->dbManager->switchToCentralDatabase();
                
                return $existingCA;
            }

            Log::info("Generating Root CA for tenant: {$tenant->id}");

            // Create storage directory untuk tenant Root CA
            $storagePath = $this->getTenantCAStoragePath($tenant->id);
            if (!is_dir($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

            // Certificate file paths
            $keyPath = $storagePath . 'rootCA.key';
            $certPath = $storagePath . 'rootCA.crt';
            $pubKeyPath = $storagePath . 'rootCA.pub';

            // Build Distinguished Name (DN) dari tenant data
            $dn = $this->buildDN($tenant);

            // Generate private key (4096-bit RSA)
            $keyGenCommand = "openssl genrsa -out " . escapeshellarg($keyPath) . " 4096 2>&1";
            exec($keyGenCommand, $keyOutput, $keyReturnCode);

            if ($keyReturnCode !== 0) {
                throw new Exception("Failed to generate Root CA private key: " . implode("\n", $keyOutput));
            }

            // Generate self-signed certificate (valid for 10 years)
            $validDays = 3650; // 10 years
            $certGenCommand = sprintf(
                'openssl req -x509 -new -nodes -key %s -sha256 -days %d -out %s -subj %s 2>&1',
                escapeshellarg($keyPath),
                $validDays,
                escapeshellarg($certPath),
                escapeshellarg($dn)
            );

            exec($certGenCommand, $certOutput, $certReturnCode);

            if ($certReturnCode !== 0) {
                throw new Exception("Failed to generate Root CA certificate: " . implode("\n", $certOutput));
            }

            // Extract public key
            $pubKeyCommand = sprintf(
                'openssl rsa -in %s -pubout -out %s 2>&1',
                escapeshellarg($keyPath),
                escapeshellarg($pubKeyPath)
            );

            exec($pubKeyCommand, $pubKeyOutput, $pubKeyReturnCode);

            if ($pubKeyReturnCode !== 0) {
                Log::warning("Failed to extract public key, but CA is still valid");
            }

            // Get certificate validity dates
            $validFrom = now();
            $validUntil = now()->addDays($validDays);

            // Parse DN components
            $dnComponents = $this->parseDN($dn);

            // Create database record
            $rootCA = RootCertificateAuthority::on('tenant')->create([
                'ca_name' => $tenant->name . ' Root CA',
                'certificate_path' => $certPath,
                'private_key_path' => $keyPath,
                'public_key_path' => $pubKeyPath,
                'dn_country' => $dnComponents['C'] ?? 'ID',
                'dn_state' => $dnComponents['ST'] ?? null,
                'dn_locality' => $dnComponents['L'] ?? null,
                'dn_organization' => $dnComponents['O'] ?? $tenant->name,
                'dn_organizational_unit' => $dnComponents['OU'] ?? null,
                'dn_common_name' => $dnComponents['CN'] ?? $tenant->name . ' Root CA',
                'valid_from' => $validFrom,
                'valid_until' => $validUntil,
                'is_self_signed' => true,
                'key_size' => 4096,
                'signature_algorithm' => 'sha256',
                'status' => 'active',
                'last_serial_number' => 1000,
                'metadata' => [
                    'generated_at' => now()->toIso8601String(),
                    'generated_by' => 'system',
                    'tenant_id' => $tenant->id,
                ],
            ]);

            // Update tenant record (switch to central first)
            $this->dbManager->switchToCentralDatabase();
            
            $tenant->update([
                'has_root_ca' => true,
                'root_ca_created_at' => now(),
            ]);

            Log::info("Root CA generated successfully for tenant: {$tenant->id}");

            return $rootCA;
        } catch (Exception $e) {
            // Make sure to switch back to central on error
            $this->dbManager->switchToCentralDatabase();
            
            Log::error("Failed to generate Root CA for tenant {$tenant->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Root CA untuk tenant (active).
     *
     * @param string $tenantId
     * @return RootCertificateAuthority|null
     */
    public function getTenantRootCA(string $tenantId): ?RootCertificateAuthority
    {
        $this->dbManager->switchToTenantDatabase($tenantId);
        
        $rootCA = RootCertificateAuthority::on('tenant')->where('status', 'active')->first();
        
        $this->dbManager->switchToCentralDatabase();
        
        return $rootCA;
    }

    /**
     * Build Distinguished Name (DN) string dari tenant data.
     *
     * @param Tenant $tenant
     * @return string
     */
    protected function buildDN(Tenant $tenant): string
    {
        $components = [];

        // Country (required)
        $components[] = 'C=' . ($tenant->company_country ?? 'ID');

        // State/Province
        if ($tenant->company_state) {
            $components[] = 'ST=' . $tenant->company_state;
        }

        // Locality (City)
        if ($tenant->company_city) {
            $components[] = 'L=' . $tenant->company_city;
        }

        // Organization (required)
        $organization = $tenant->company_legal_name ?? $tenant->name;
        $components[] = 'O=' . $organization;

        // Organizational Unit
        if ($tenant->company_organization_unit) {
            $components[] = 'OU=' . $tenant->company_organization_unit;
        }

        // Common Name (required) - Nama Root CA
        $components[] = 'CN=' . $tenant->name . ' Root CA';

        return '/' . implode('/', $components);
    }

    /**
     * Parse DN string ke array components.
     *
     * @param string $dn
     * @return array
     */
    protected function parseDN(string $dn): array
    {
        $components = [];
        $parts = explode('/', trim($dn, '/'));

        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                [$key, $value] = explode('=', $part, 2);
                $components[$key] = $value;
            }
        }

        return $components;
    }

    /**
     * Get storage path untuk tenant Root CA.
     *
     * @param string $tenantId
     * @return string
     */
    public function getTenantCAStoragePath(string $tenantId): string
    {
        return storage_path("app/private/tenants/{$tenantId}/rootca/");
    }

    /**
     * Get global Root CA path (untuk personal mode / legacy certificates).
     *
     * @return string
     */
    public function getGlobalRootCAPath(): string
    {
        return storage_path('app/private/secure/rootCA.crt');
    }

    /**
     * Check apakah tenant sudah punya Root CA.
     *
     * @param string $tenantId
     * @return bool
     */
    public function tenantHasRootCA(string $tenantId): bool
    {
        $this->dbManager->switchToTenantDatabase($tenantId);
        
        $exists = RootCertificateAuthority::on('tenant')->where('status', 'active')->exists();
        
        $this->dbManager->switchToCentralDatabase();
        
        return $exists;
    }

    /**
     * Revoke tenant Root CA.
     *
     * @param string $tenantId
     * @return bool
     */
    public function revokeTenantRootCA(string $tenantId): bool
    {
        $this->dbManager->switchToTenantDatabase($tenantId);
        
        $result = RootCertificateAuthority::on('tenant')->where('status', 'active')
            ->update(['status' => 'revoked']);
        
        $this->dbManager->switchToCentralDatabase();
        
        return $result > 0;
    }
}
