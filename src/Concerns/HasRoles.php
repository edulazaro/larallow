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
            return $this->morphToMany(Role::class, 'actor', 'actor_role')
                ->withPivot(['scope_type', 'scope_id']);
        }

        return Roles::query()->for($this)->roles($roles);
    }

    /**
     * Assign a role to the model, optionally scoped by a scope model.
     *
     * @param int|Role $role
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return void
     */
    public function assignRole(int|Role $role, $scope = null): void
    {
        $roleId = $role instanceof Role ? $role->id : $role;

        // Check if already assigned for this specific scope
        $exists = $this->roles()
            ->where('roles.id', $roleId)
            ->where(function ($query) use ($scope) {
                if ($scope) {
                    $query->where('actor_role.scope_type', $scope->getMorphClass())
                          ->where('actor_role.scope_id', $scope->getKey());
                } else {
                    $query->whereNull('actor_role.scope_type')
                          ->whereNull('actor_role.scope_id');
                }
            })
            ->exists();

        if (!$exists) {
            $pivotData = $scope ? [
                'scope_type' => $scope->getMorphClass(),
                'scope_id' => $scope->getKey(),
            ] : [];

            $this->roles()->attach($roleId, $pivotData);
        }

        $this->load('roles');
    }

    /**
     * Assign multiple roles to the model, optionally scoped by a scope model.
     *
     * @param array<int|Role> $roles
     * @param mixed|null $scope Polymorphic model instance or null.
     * @return void
     */
    public function assignRoles(array $roles, $scope = null): void
    {
        foreach ($roles as $role) {
            $this->assignRole($role, $scope);
        }
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
                    && (int) $role->pivot->scope_id === (int) $scope->getKey();
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

    public function syncRoles(array|int|Role $roles, $scope = null): void
    {

        $roleIds = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn($role) => $role instanceof Role ? $role->id : $role)
            ->unique()
            ->values()
            ->all();

        $pivotTable = 'actor_role';

        $currentRoleIds = $this->roles()
            ->where(function ($query) use ($scope, $pivotTable) {
                if ($scope) {
                    $query->where("$pivotTable.scope_type", $scope->getMorphClass())
                        ->where("$pivotTable.scope_id", $scope->getKey());
                } else {
                    $query->whereNull("$pivotTable.scope_type")
                        ->whereNull("$pivotTable.scope_id");
                }
            })
            ->pluck('roles.id')
            ->toArray();

        $toRemove = array_diff($currentRoleIds, $roleIds);
        $toAdd = array_diff($roleIds, $currentRoleIds);

        if (!empty($toRemove)) {
            $detachQuery = $this->roles();

            $detachQuery->wherePivotIn('role_id', $toRemove);

            if ($scope) {
                $detachQuery->wherePivot('scope_type', $scope->getMorphClass())
                            ->wherePivot('scope_id', $scope->getKey());
            } else {
                $detachQuery->wherePivotNull('scope_type')
                            ->wherePivotNull('scope_id');
            }

            $detachQuery->detach();
        }

        foreach ($toAdd as $roleId) {
            $this->assignRole($roleId, $scope);
        }

        $this->load('roles');
    }
}