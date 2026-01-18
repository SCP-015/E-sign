<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'name',
        'image_path',
        'image_type',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope: Signature yang bisa dipakai di context tertentu
     * 
     * LOGIC:
     * - Personal mode: HANYA signature personal (tenant_id = NULL)
     * - Tenant mode: signature personal (portable) + signature tenant ini
     */
    public function scopeAvailableForContext($query, int $userId, ?string $tenantId)
    {
        $query->where('user_id', $userId);

        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }

        return $query->where(function($q) use ($tenantId) {
            $q->whereNull('tenant_id')
              ->orWhere('tenant_id', $tenantId);
        });
    }

    /**
     * Helper: Check apakah signature personal (portable)
     */
    public function isPersonal(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * Helper: Check apakah signature portable
     */
    public function isPortable(): bool
    {
        return $this->isPersonal();
    }
}
