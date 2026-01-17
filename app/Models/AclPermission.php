<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AclPermission extends Model
{
    protected $table = 'acl_permissions';

    protected $fillable = [
        'name',
        'guard_name',
        'description',
    ];

    /**
     * Get roles that have this permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AclRole::class,
            'acl_role_has_permissions',
            'permission_id',
            'role_id'
        );
    }
}
