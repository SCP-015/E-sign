<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Models\Tenant;
use App\Traits\HasAclPermissions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasAclPermissions, HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * IMPORTANT: Users always in central database.
     * OAuth tokens, sessions, and authentication MUST use central DB.
     */
    protected $connection = 'pgsql';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'avatar',
        'kyc_status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function documents()
    {
        return $this->hasMany(\App\Models\Document::class);
    }

    public function signatures()
    {
        return $this->hasMany(\App\Models\Signature::class);
    }

    public function certificate()
    {
        return $this->hasOne(\App\Models\Certificate::class)->latest();
    }

    /**
     * Get the tenants that the user belongs to.
     */
    public function tenants()
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot('role', 'is_owner', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get tenant user records for this user.
     */
    public function tenantUsers()
    {
        return $this->hasMany(\App\Models\Tenant\User::class);
    }

    /**
     * Get tenants owned by this user.
     */
    public function ownedTenants()
    {
        return $this->hasMany(Tenant::class, 'owner_id');
    }

    /**
     * Check if user can create more tenants.
     * Max limit is 5 owned tenants per user.
     */
    public function canCreateTenant(): bool
    {
        return $this->ownedTenants()->count() < 5;
    }

    /**
     * Get the current active tenant from session.
     */
    public function getCurrentTenant()
    {
        $tenantId = session('current_tenant_id') ?? $this->current_tenant_id;
        if ($tenantId) {
            return $this->tenants()->where('tenants.id', $tenantId)->first();
        }
        return null;
    }
}
