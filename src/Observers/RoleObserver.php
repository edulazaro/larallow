<?php

namespace EduLazaro\Larallow\Observers;

use EduLazaro\Larallow\Models\Role;

class RoleObserver
{
    /**
     * Handle the Role "deleting" event.
     *
     * @param  Role  $role
     * @return void
     */
    public function deleting(Role $role)
    {
        $role->permissions()->delete();
        $role->actorRoles()->delete();
    }
}
