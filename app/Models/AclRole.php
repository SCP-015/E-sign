<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AclRole extends Model
{
    protected $table = 'acl_roles';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * Get permissions for this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AclPermission::class,
            'acl_role_has_permissions',
            'role_id',
            'permission_id'
        );
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('name', $permissionName)->exists();
    }
}
