<?php

namespace EduLazaro\Larallow\Concerns;

use EduLazaro\Larallow\Models\Role;

trait IsRoleTenant
{
    /**
     * Get the roles associated with this tenant.
     */
    public function roles()
    {
        return $this->morphMany(Role::class, 'tenant');
    }
}
