<?php

namespace EduLazaro\Larallow\Models;

use EduLazaro\Larallow\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $fillable = ['role_id', 'permission'];

    /**
     * Get the role that owns this permission.
     *
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
