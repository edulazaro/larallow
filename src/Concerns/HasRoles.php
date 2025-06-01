<?php

namespace EduLazaro\Larallow\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use EduLazaro\Larallow\Models\Role;
use EduLazaro\Larallow\Roles;
use BackedEnum;

trait HasRoles
{
    /**
     * Get the roles relationship or filter by given roles.
     *
     * @param string|array|null $roles Role names or IDs to filter by.
     * @return MorphToMany|Roles
     */
    public function roles(string|array|null $roles = null): MorphToMany|Roles
    {
        if (is_null($roles)) {
            return $this->morphToMany(Role::class, 'actor', 'actor_role');
        }

        return Roles::query()->roles($roles)->for($this);
    }

    /**
     * Assign a role to the model, optionally scoped by a roleable model.
     *
     * @param Role $role
     * @param mixed|null $roleable Polymorphic model instance or null.
     * @return void
     */
    public function assignRole(Role $role, $roleable = null): void
    {
        $pivotData = [];

        if ($roleable) {
            $pivotData = [
                'roleable_type' => $roleable->getMorphClass(),
                'roleable_id' => $roleable->getKey(),
            ];
        }

        $this->roles()->syncWithoutDetaching([
            $role->id => $pivotData,
        ]);

        $this->load('roles');
    }

    /**
     * Remove a role from the model, optionally scoped by a roleable model.
     *
     * @param Role $role
     * @param mixed|null $roleable Polymorphic model instance or null.
     * @return void
     */
    public function removeRole(Role $role, $roleable = null): void
    {
        if ($roleable) {
            $this->roles()
                ->wherePivot('role_id', $role->id)
                ->wherePivot('roleable_type', $roleable->getMorphClass())
                ->wherePivot('roleable_id', $roleable->getKey())
                ->detach();
        } else {
            $this->roles()
                ->wherePivot('role_id', $role->id)
                ->wherePivotNull('roleable_type')
                ->wherePivotNull('roleable_id')
                ->detach();
        }

        $this->load('roles');
    }

    /**
     * Check if the model has a given role name, optionally scoped by a roleable model.
     *
     * @param string $roleName
     * @param mixed|null $roleable Polymorphic model instance or null.
     * @return bool
     */
    public function hasRole(string $roleName, $roleable = null): bool
    {
        return $this->roles
            ->filter(function ($role) use ($roleable) {
                if ($roleable === null) {
                    return $role->pivot->roleable_type === null && $role->pivot->roleable_id === null;
                }

                return $role->pivot->roleable_type === $roleable->getMorphClass()
                    && $role->pivot->roleable_id === $roleable->getKey();
            })
            ->contains('handle', $roleName);
    }

    /**
     * Check if the model has a given permission via assigned roles,
     * optionally scoped by a permissionable model.
     *
     * @param string|BackedEnum $permission
     * @param mixed|null $permissionable Polymorphic model instance or null.
     * @return bool
     */
    public function hasRolePermission(string|BackedEnum $permission, $permissionable = null): bool
    {
        return $this->hasRolePermissions($permission, $permissionable);
    }

    /**
     * Check if the model has all of the given permissions via assigned roles,
     * optionally scoped by a permissionable model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $permissionable Polymorphic model instance or null.
     * @return bool
     */
    public function hasRolePermissions(string|BackedEnum|array $permissions, $permissionable = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $values = array_unique($values);

        $permissionsFromRoles = $this->roles()
            ->when($permissionable, function ($query) use ($permissionable) {
                $query->wherePivot('roleable_type', $permissionable->getMorphClass())
                    ->wherePivot('roleable_id', $permissionable->getKey());
            })
            ->with('permissions')
            ->whereHas('permissions', function ($query) use ($values) {
                $query->whereIn('permission', $values);
            })
            ->get()
            ->pluck('permissions.*.permission')
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $missing = array_diff($values, $permissionsFromRoles);

        return count($missing) === 0;
    }

    /**
     * Check if the model has any of the given permissions via assigned roles,
     * optionally scoped by a permissionable model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $permissionable Polymorphic model instance or null.
     * @return bool
     */
    public function hasAnyRolePermission(string|BackedEnum $permission, $permissionable = null): bool
    {
        return $this->hasAnyRolePermissions($permission, $permissionable);
    }

    /**
     * Check if the model has any of the given permissions via assigned roles,
     * optionally scoped by a permissionable model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $permissionable Polymorphic model instance or null.
     * @return bool
     */
    public function hasAnyRolePermissions(string|BackedEnum|array $permissions, $permissionable = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        return $this->roles()
            ->when($permissionable, function ($query) use ($permissionable) {
                $query->wherePivot('roleable_type', $permissionable->getMorphClass())
                    ->wherePivot('roleable_id', $permissionable->getKey());
            })
            ->whereHas('permissions', function ($query) use ($values) {
                $query->whereIn('permission', $values);
            })
            ->exists();
    }
}