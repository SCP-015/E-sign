<?php

namespace App\Models;

use App\Traits\UsesTenantAwareConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class QuotaSetting extends Model
{
    use HasUlids, UsesTenantAwareConnection;

    protected $table = 'quota_settings';

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'max_documents_per_user',
        'max_signatures_per_user',
        'max_document_size_mb',
        'max_total_storage_mb',
    ];

    protected $casts = [
        'max_documents_per_user' => 'integer',
        'max_signatures_per_user' => 'integer',
        'max_document_size_mb' => 'integer',
        'max_total_storage_mb' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function getOrCreateForTenant(string $tenantId): self
    {
        return self::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'max_documents_per_user' => 50,
                'max_signatures_per_user' => 100,
                'max_document_size_mb' => 10,
                'max_total_storage_mb' => 500,
            ]
        );
    }
}
