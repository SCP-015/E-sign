<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    protected $caKeyPath;
    protected $caCertPath;
    protected $securePath;

    public function __construct()
    {
        // Use the centralized disk path
        $this->securePath = Storage::disk('central_secure')->path('');
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

        $certId = Str::random(16);
        $keyPath = $this->securePath . "{$certId}.key";
        $csrPath = $this->securePath . "{$certId}.csr";
        $certPath = $this->securePath . "{$certId}.crt";

        // 1. Generate Private Key
        exec("openssl genrsa -out {$keyPath} 2048");

        // 2. Generate CSR
        $subj = "/C=US/ST=State/L=City/O=MyOrg/OU=Users/CN={$user->name}/emailAddress={$user->email}";
        $subj = escapeshellarg($subj);
        exec("openssl req -new -key {$keyPath} -out {$csrPath} -subj {$subj}");

        // 3. Sign CSR
        // Validity: 365 Days (1 Year) as per policy
        $command = "openssl x509 -req -in {$csrPath} -CA {$this->caCertPath} -CAkey {$this->caKeyPath} -CAcreateserial -out {$certPath} -days 365 -sha256";
        exec($command);

        if (file_exists($certPath) && file_exists($keyPath)) {
            // Create Database Record
            return \App\Models\Certificate::create([
                'user_id' => $user->id,
                'private_key_path' => $keyPath,
                'public_key_path' => $csrPath,
                'certificate_path' => $certPath,
                'status' => 'active',
            ]);
        }

        throw new \Exception("Failed to generate certificate files.");
    }
    
    public function getRootCertPath()
    {
        return $this->caCertPath;
    }
}
