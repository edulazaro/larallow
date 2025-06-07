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
     * Assign a role to the model, optionally scoped by a scope model.
     *
     * @param Role $role
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return void
     */
    public function assignRole(Role $role, $scope = null): void
    {
        $pivotData = [];

        if ($scope) {
            $pivotData = [
                'scope_type' => $scope->getMorphClass(),
                'scope_id' => $scope->getKey(),
            ];
        }

        $this->roles()->syncWithoutDetaching([
            $role->id => $pivotData,
        ]);

        $this->load('roles');
    }

    /**
     * Remove a role from the model, optionally scoped by a scope model.
     *
     * @param Role $role
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return void
     */
    public function removeRole(Role $role, $scope = null): void
    {
        if ($scope) {
            $this->roles()
                ->wherePivot('role_id', $role->id)
                ->wherePivot('scope_type', $scope->getMorphClass())
                ->wherePivot('scope_id', $scope->getKey())
                ->detach();
        } else {
            $this->roles()
                ->wherePivot('role_id', $role->id)
                ->wherePivotNull('scope_type')
                ->wherePivotNull('scope_id')
                ->detach();
        }

        $this->load('roles');
    }

    /**
     * Check if the model has a given role name, optionally scoped by a scope model.
     *
     * @param string $roleName
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return bool
     */
    public function hasRole(string $roleName, $scope = null): bool
    {
        return $this->roles
            ->filter(function ($role) use ($scope) {
                if ($scope === null) {
                    return $role->pivot->scope_type === null && $role->pivot->scope_id === null;
                }

                return $role->pivot->scope_type === $scope->getMorphClass()
                    && $role->pivot->scope_id === $scope->getKey();
            })
            ->contains('handle', $roleName);
    }

    /**
     * Check if the model has a given permission via assigned roles,
     * optionally scoped by a scope model.
     *
     * @param string|BackedEnum $permission
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return bool
     */
    public function hasRolePermission(string|BackedEnum $permission, $scope = null): bool
    {
        return $this->hasRolePermissions($permission, $scope);
    }

    /**
     * Check if the model has all of the given permissions via assigned roles,
     * optionally scoped by a scope model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return bool
     */
    public function hasRolePermissions(string|BackedEnum|array $permissions, $scope = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $values = array_unique($values);

        $permissionsFromRoles = $this->roles()
            ->when($scope, function ($query) use ($scope) {
                $query->wherePivot('scope_type', $scope->getMorphClass())
                    ->wherePivot('scope_id', $scope->getKey());
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
     * optionally scoped by a scope model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return bool
     */
    public function hasAnyRolePermission(string|BackedEnum $permission, $scope = null): bool
    {
        return $this->hasAnyRolePermissions($permission, $scope);
    }

    /**
     * Check if the model has any of the given permissions via assigned roles,
     * optionally scoped by a scope model.
     *
     * @param string|BackedEnum|array $permissions
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return bool
     */
    public function hasAnyRolePermissions(string|BackedEnum|array $permissions, $scope = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $values = [];
        foreach ($permissions as $permission) {
            $values[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        return $this->roles()
            ->when($scope, function ($query) use ($scope) {
                $query->wherePivot('scope_type', $scope->getMorphClass())
                    ->wherePivot('scope_id', $scope->getKey());
            })
            ->whereHas('permissions', function ($query) use ($values) {
                $query->whereIn('permission', $values);
            })
            ->exists();
    }
}