<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserQuotaOverride extends Model
{
    protected $table = 'user_quota_overrides';

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
