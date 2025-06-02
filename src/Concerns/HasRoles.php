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
    public function roles(int|array|null $roles = null): MorphToMany|Roles
    {
        if (is_null($roles)) {
            return $this->morphToMany(Role::class, 'actor', 'actor_role');
        }

        return Roles::query()->for($this)->roles($roles);
    }

    /**
     * Assign a role to the model, optionally scoped by a scopable model.
     *
     * @param Role $role
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return void
     */
    public function assignRole(Role $role, $scopable = null): void
    {
        $pivotData = [];

        if ($scopable) {
            $pivotData = [
                'scopable_type' => $scopable->getMorphClass(),
                'scopable_id' => $scopable->getKey(),
            ];
        }

        $this->roles()->syncWithoutDetaching([
            $role->id => $pivotData,
        ]);

        $this->load('roles');
    }

    /**
     * Remove a role from the model, optionally scoped by a scopable model.
     *
     * @param Role $role
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return void
     */
    public function removeRole(Role $role, $scopable = null): void
    {
        if ($scopable) {
            $this->roles()
                ->wherePivot('role_id', $role->id)
                ->wherePivot('scopable_type', $scopable->getMorphClass())
                ->wherePivot('scopable_id', $scopable->getKey())
                ->detach();
        } else {
            $this->roles()
                ->wherePivot('role_id', $role->id)
                ->wherePivotNull('scopable_type')
                ->wherePivotNull('scopable_id')
                ->detach();
        }

        $this->load('roles');
    }

    /**
     * Check if the model has a given role name, optionally scoped by a scopable model.
     *
     * @param string $roleName
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return bool
     */
    public function hasRole(string $roleName, $scopable = null): bool
    {
        return $this->roles
            ->filter(function ($role) use ($scopable) {
                if ($scopable === null) {
                    return $role->pivot->scopable_type === null && $role->pivot->scopable_id === null;
                }

                return $role->pivot->scopable_type === $scopable->getMorphClass()
                    && $role->pivot->scopable_id === $scopable->getKey();
            })
            ->contains('handle', $roleName);
    }

    /**
     * Check if the model has a given permission via assigned roles,
     * optionally scoped by a scopable model.
     *
     * @param string|BackedEnum $permission
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return bool
     */
    public function hasRolePermission(string|BackedEnum $permission, $scopable = null): bool
    {
        return $this->hasRolePermissions($permission, $scopable);
    }

    /**
     * Check if the model has all of the given permissions via assigned roles,
     * optionally scoped by a scopable model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return bool
     */
    public function hasRolePermissions(string|BackedEnum|array $permissions, $scopable = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $values = array_unique($values);

        $permissionsFromRoles = $this->roles()
            ->when($scopable, function ($query) use ($scopable) {
                $query->wherePivot('scopable_type', $scopable->getMorphClass())
                    ->wherePivot('scopable_id', $scopable->getKey());
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
     * optionally scoped by a scopable model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return bool
     */
    public function hasAnyRolePermission(string|BackedEnum $permission, $scopable = null): bool
    {
        return $this->hasAnyRolePermissions($permission, $scopable);
    }

    /**
     * Check if the model has any of the given permissions via assigned roles,
     * optionally scoped by a scopable model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $scopable Polymorphic model instance or null.
     * @return bool
     */
    public function hasAnyRolePermissions(string|BackedEnum|array $permissions, $scopable = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        return $this->roles()
            ->when($scopable, function ($query) use ($scopable) {
                $query->wherePivot('scopable_type', $scopable->getMorphClass())
                    ->wherePivot('scopable_id', $scopable->getKey());
            })
            ->whereHas('permissions', function ($query) use ($values) {
                $query->whereIn('permission', $values);
            })
            ->exists();
    }
}