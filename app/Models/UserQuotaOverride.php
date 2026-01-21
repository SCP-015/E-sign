<?php

namespace App\Models;

use App\Traits\UsesTenantAwareConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UserQuotaOverride extends Model
{
    use HasUlids, UsesTenantAwareConnection;

    protected $table = 'user_quota_overrides';

    protected $connection = 'tenant';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'max_documents_per_user',
        'max_signatures_per_user',
        'max_total_storage_mb',
    ];

    protected $casts = [
        'max_documents_per_user' => 'integer',
        'max_signatures_per_user' => 'integer',
        'max_total_storage_mb' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
