<?php

namespace EduLazaro\Larallow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActorPermission extends Model
{
    protected $table = 'actor_permissions';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'permissionable_type',
        'permissionable_id',
        'permission',
    ];

    /**
     * Get the owning actor model.
     *
     * @return MorphTo
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the permissionable model this permission is scoped to.
     *
     * @return MorphTo
     */
    public function permissionable(): MorphTo
    {
        return $this->morphTo();
    }
}
