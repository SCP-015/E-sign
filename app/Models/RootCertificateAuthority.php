<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\UsesTenantAwareConnection;

class RootCertificateAuthority extends Model
{
    use HasUlids, UsesTenantAwareConnection;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ca_name',
        'certificate_path',
        'private_key_path',
        'public_key_path',
        'dn_country',
        'dn_state',
        'dn_locality',
        'dn_organization',
        'dn_organizational_unit',
        'dn_common_name',
        'valid_from',
        'valid_until',
        'is_self_signed',
        'key_size',
        'signature_algorithm',
        'status',
        'last_serial_number',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_self_signed' => 'boolean',
        'key_size' => 'integer',
        'last_serial_number' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get certificates issued by this Root CA.
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class, 'root_ca_id');
    }

    /**
     * Check if Root CA is currently valid.
     */
    public function isValid(): bool
    {
        return $this->status === 'active'
            && $this->valid_from <= now()
            && $this->valid_until >= now();
    }

    /**
     * Check if Root CA is expired.
     */
    public function isExpired(): bool
    {
        return $this->valid_until < now();
    }

    /**
     * Check if Root CA will expire soon (within 30 days).
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->valid_until <= now()->addDays($days);
    }

    /**
     * Get next serial number untuk issue certificate baru.
     */
    public function getNextSerialNumber(): int
    {
        $this->increment('last_serial_number');
        return $this->last_serial_number;
    }

    /**
     * Get Distinguished Name (DN) string.
     */
    public function getDnString(): string
    {
        $components = [];

        if ($this->dn_country) {
            $components[] = "C={$this->dn_country}";
        }
        if ($this->dn_state) {
            $components[] = "ST={$this->dn_state}";
        }
        if ($this->dn_locality) {
            $components[] = "L={$this->dn_locality}";
        }
        if ($this->dn_organization) {
            $components[] = "O={$this->dn_organization}";
        }
        if ($this->dn_organizational_unit) {
            $components[] = "OU={$this->dn_organizational_unit}";
        }
        if ($this->dn_common_name) {
            $components[] = "CN={$this->dn_common_name}";
        }

        return '/' . implode('/', $components);
    }

    /**
     * Revoke Root CA (mark as revoked).
     */
    public function revoke(): bool
    {
        return $this->update(['status' => 'revoked']);
    }

    /**
     * Get days remaining until expiration.
     */
    public function getDaysUntilExpiration(): int
    {
        return now()->diffInDays($this->valid_until, false);
    }
}
