<?php

namespace App\Models;

use App\Traits\UsesTenantAwareConnection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class UserQuotaUsage extends Model
{
    use HasUlids, UsesTenantAwareConnection;

    protected $connection = 'tenant';

    protected $table = 'user_quota_usage';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'documents_uploaded',
        'signatures_created',
        'storage_used_mb',
    ];

    protected $casts = [
        'documents_uploaded' => 'integer',
        'signatures_created' => 'integer',
        'storage_used_mb' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function getOrCreateForUser(string $userId, string $tenantId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId, 'tenant_id' => $tenantId],
            [
                'documents_uploaded' => 0,
                'signatures_created' => 0,
                'storage_used_mb' => 0,
            ]
        );
    }
}
