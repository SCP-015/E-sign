<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

class AclPermission extends Model
{
    use HasUlids;

    protected $table = 'acl_permissions';

    public $incrementing = false;
    protected $keyType = 'string';

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
