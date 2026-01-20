<?php

namespace App\Models\Tenant;

use App\Models\Tenant;
use App\Models\User as CentralUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Invitation extends Model
{
    use HasUlids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tenant_invitations';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tenant_id',
        'code',
        'role',
        'expires_at',
        'max_uses',
        'used_count',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'used_count' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->code)) {
                $invitation->code = static::generateCode();
            }
        });
    }

    /**
     * Get the tenant that owns the invitation.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who created the invitation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }

    /**
     * Check if the invitation is still valid.
     */
    public function isValid(): bool
    {
        // Check if expired
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check if max uses reached
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Increment the usage counter.
     */
    public function incrementUse(): void
    {
        $this->increment('used_count');
    }

    /**
     * Generate unique invitation code.
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
