<?php

namespace App\Models;

use App\Helpers\StoragePathHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'title',
        'file_path',
        'original_filename',
        'file_size',
        'mime_type',
        'file_type',
        'file_size_bytes',
        'page_count',
        'signed_path',
        'final_pdf_path',
        'verify_token',
        'signing_mode',
        'status',
        'signed_at',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'signed_at' => 'datetime',
        'file_size' => 'integer',
        'file_size_bytes' => 'integer',
        'page_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function signers()
    {
        return $this->hasMany(DocumentSigner::class);
    }

    public function placements()
    {
        return $this->hasMany(SignaturePlacement::class);
    }

    public function signingEvidence()
    {
        return $this->hasOne(DocumentSigningEvidence::class);
    }

    public function isAllSigned()
    {
        return $this->signers()->where('status', 'PENDING')->count() === 0;
    }

    /**
     * Scope: Filter dokumen berdasarkan context saat ini (STRICT ISOLATION)
     * - Personal mode: HANYA dokumen dengan tenant_id = NULL
     * - Tenant mode: HANYA dokumen dengan tenant_id = {tenant_uuid}
     */
    public function scopeForCurrentContext($query, ?string $tenantId)
    {
        if ($tenantId === null) {
            return $query->whereNull('tenant_id');
        }
        
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Dokumen yang bisa diakses user di context tertentu
     */
    public function scopeAccessibleByUser($query, int $userId, ?string $tenantId)
    {
        return $query->forCurrentContext($tenantId)
            ->where(function($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhereHas('signers', function($sq) use ($userId) {
                      $sq->where('user_id', $userId);
                  });
            });
    }

    /**
     * Helper: Get storage path untuk dokumen ini
     */
    public function getStoragePath(string $type = 'original'): string
    {
        return StoragePathHelper::getDocumentPath($this->tenant_id, $type);
    }

    /**
     * Helper: Check apakah dokumen personal
     */
    public function isPersonal(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * Helper: Check apakah dokumen tenant
     */
    public function isTenant(): bool
    {
        return $this->tenant_id !== null;
    }
}
