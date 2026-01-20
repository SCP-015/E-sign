<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Concerns\HasInternalKeys;
use Stancl\Tenancy\Database\Concerns\TenantRun;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasFactory, HasDatabase, HasDomains, HasInternalKeys, TenantRun;
    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'code',
        'slug',
        'description',
        'logo',
        'banner',
        'website',
        'phone',
        'address',
        'facebook',
        'twitter',
        'instagram',
        'linkedin',
        'owner_id',
        'plan',
        'data',
        // Company/Organization DN fields for Root CA
        'company_legal_name',
        'company_country',
        'company_state',
        'company_city',
        'company_address',
        'company_postal_code',
        'company_organization_unit',
        'has_root_ca',
        'root_ca_created_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'root_ca_created_at' => 'datetime',
        'has_root_ca' => 'boolean',
    ];

    /**
     * Get the name of the tenant's database.
     */
    public function databaseName(): string
    {
        $prefix = config('tenancy.database.prefix', 'tenant_');
        return $prefix . str_replace('-', '_', strtolower((string) $this->id));
    }

    /**
     * The custom columns that should be treated as actual database columns.
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'code',
            'slug',
            'description',
            'owner_id',
            'plan',
            'created_at',
            'updated_at',
            'logo',
            'banner',
            'website',
            'phone',
            'address',
            'facebook',
            'twitter',
            'instagram',
            'linkedin',
            'company_legal_name',
            'company_country',
            'company_state',
            'company_city',
            'company_address',
            'company_postal_code',
            'company_organization_unit',
            'has_root_ca',
            'root_ca_created_at',
            'tenancy_db_name',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tenant) {
            if (empty($tenant->id) || !Str::isUlid((string) $tenant->id)) {
                $tenant->id = (string) Str::ulid();
            }
            
            // Set tenancy_db_name for Stancl\Tenancy\Database\Concerns\HasDatabase
            $tenant->tenancy_db_name = $tenant->databaseName();

            if (empty($tenant->code)) {
                $tenant->code = static::generateCode();
            }
            if (empty($tenant->slug)) {
                $tenant->slug = static::generateSlug($tenant->name);
            }
        });
    }

    /**
     * Get the owner of the tenant.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the users for the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_users')
            ->withPivot('role', 'is_owner', 'joined_at')
            ->withTimestamps();
    }

    /**
     * Get tenant users relationship.
     */
    public function tenantUsers(): HasMany
    {
        return $this->hasMany(\App\Models\Tenant\User::class);
    }

    /**
     * Get invitations for the tenant.
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(\App\Models\Tenant\Invitation::class);
    }

    /**
     * Check if user is owner of this tenant.
     */
    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /**
     * Get admins of this tenant.
     */
    public function getAdmins()
    {
        return $this->tenantUsers()->where('role', 'admin')->with('user')->get();
    }

    /**
     * Get the name of the key used as the tenant identifier.
     */
    public function getTenantKeyName(): string
    {
        return 'id';
    }

    /**
     * Get the value of the key used as the tenant identifier.
     */
    public function getTenantKey()
    {
        return $this->getAttribute($this->getTenantKeyName());
    }

    /**
     * Generate unique join code.
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * Generate unique slug from name.
     */
    public static function generateSlug(string $name): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
