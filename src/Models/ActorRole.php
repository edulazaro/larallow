<?php

namespace EduLazaro\Larallow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActorRole extends Model
{
    protected $table = 'actor_role';

    protected $fillable = [
        'actor_type',
        'actor_id',
        'role_id',
        'scope_type',
        'scope_id',
    ];

    /**
     * Get the actor that this role is assigned to.
     *
     * @return MorphTo
     */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the role associated with this assignment.
     *
     * @return BelongsTo
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the scope entity that scope_type this role assignment.
     *
     * @return MorphTo
     */
    public function scope(): MorphTo
    {
        return $this->morphTo();
    }
}
