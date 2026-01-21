<?php

namespace App\Models;

use App\Helpers\StoragePathHelper;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\UsesTenantAwareConnection;
use Illuminate\Support\Facades\Auth;

class Document extends Model
{
    use HasFactory, UsesTenantAwareConnection, HasUlids;

    protected $fillable = [
        'user_id',
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
     * Scope: Filter dokumen berdasarkan context saat ini.
     * 
     * NOTE: In multi-DB architecture, isolation is handled by connection switching.
     * Personal mode queries central DB, tenant mode queries tenant DB.
     * No need for tenant_id column filtering.
     */
    public function scopeForCurrentContext($query, ?string $tenantId)
    {
        // In multi-DB setup, connection is already switched
        // No filtering needed - just return query as-is
        return $query;
    }

    /**
     * Scope: Dokumen yang bisa diakses user di context tertentu
     */
    public function scopeAccessibleByUser($query, string $userId, ?string $tenantId, ?string $userEmail = null)
    {
        $email = is_string($userEmail) ? strtolower(trim($userEmail)) : null;

        // Connection is already set to correct DB (personal vs tenant)
        return $query->where(function ($q) use ($userId, $email) {
            $q->where('user_id', $userId)
                ->orWhereHas('signers', function ($sq) use ($userId, $email) {
                    $sq->where('user_id', $userId);
                    if ($email) {
                        $sq->orWhereRaw('LOWER(email) = ?', [$email]);
                    }
                });
        });
    }

    /**
     * Scope: Dokumen yang bisa diakses user lintas context (personal/tenant)
     */
    public function scopeAccessibleByUserAnyContext($query, string $userId, ?string $userEmail = null)
    {
        $email = is_string($userEmail) ? strtolower(trim($userEmail)) : null;

        return $query->where(function ($q) use ($userId, $email) {
            $q->where('user_id', $userId)
                ->orWhereHas('signers', function ($sq) use ($userId, $email) {
                    $sq->where('user_id', $userId);
                    if ($email) {
                        $sq->orWhereRaw('LOWER(email) = ?', [$email]);
                    }
                });
        });
    }

    /**
     * Scope: Personal mode documents accessible by user
     */
    public function scopeAccessibleByUserAnyContextForPersonal($query, string $userId, ?string $userEmail = null)
    {
        $email = is_string($userEmail) ? strtolower(trim($userEmail)) : null;

        return $query->where(function ($q) use ($userId, $email) {
            $q->where('user_id', $userId)
                ->orWhereHas('signers', function ($sq) use ($userId, $email) {
                    $sq->where('user_id', $userId);
                    if ($email) {
                        $sq->orWhereRaw('LOWER(email) = ?', [$email]);
                    }
                });
        });
    }

    /**
     * Helper: Get storage path untuk dokumen ini
     */
    public function getStoragePath(string $type = 'original'): string
    {
        // Determine tenant context from connection
        $tenantId = $this->isInTenantContext() ? session('current_tenant_id') : null;
        $userEmail = Auth::user()?->email;
        
        return StoragePathHelper::getDocumentPath($tenantId, $type, $userEmail);
    }

    /**
     * Helper: Check apakah dokumen personal (in central DB)
     */
    public function isPersonal(): bool
    {
        return $this->getConnectionName() === 'pgsql';
    }

    /**
     * Helper: Check apakah dokumen tenant (in tenant DB)
     */
    public function isTenant(): bool
    {
        return $this->getConnectionName() === 'tenant';
    }
}
