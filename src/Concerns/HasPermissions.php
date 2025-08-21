<?php

namespace EduLazaro\Larallow\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use EduLazaro\Larallow\Models\ActorPermission;
use Illuminate\Database\Eloquent\Model;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Permission;
use InvalidArgumentException;
use BackedEnum;

trait HasPermissions
{
    public function permissions(string|array|null $permissions = null): MorphMany|Permissions
    {
        if (is_null($permissions)) {
            return $this->morphMany(ActorPermission::class, 'actor');
        }

        return Permissions::query()->permissions($permissions)->for($this);
    }
    
    /**
     * Allow one or multiple permissions for the actor, optionally scoped by a model.
     *
     * @param string|BackedEnum|array<string|BackedEnum> $permissions The permission(s) to allow.
     * @param mixed|null $scope Polymorphic scope model instance or null.
     * @return void
     *
     * @throws InvalidArgumentException If any permission is not registered or not allowed for the actor/scope.
     */
    public function allow(string|BackedEnum|array $permissions, $scope = null): void
    {
        $permissionsArray = is_array($permissions) ? $permissions : [$permissions];

        $actorType = $this->getMorphClass();
        $scopeType = $scope?->getMorphClass();

        foreach ($permissionsArray as $permission) {
            $permissionValue = $permission instanceof BackedEnum ? $permission->value : $permission;

            if (!Permission::exists($permissionValue)) {
                throw new InvalidArgumentException("Permission '{$permissionValue}' is not registered.");
            }

            if (!Permission::isAllowedFor($permissionValue, $actorType, $scopeType)) {
                throw new InvalidArgumentException("Permission '{$permissionValue}' is not allowed for actor type '{$actorType}' and scope type '{$scopeType}'.");
            }

            $this->permissions()->create([
                'permission' => $permissionValue,
                'scope_type' => $scopeType,
                'scope_id' => $scope?->getKey(),
            ]);
        }
    }

    public function deny(string|BackedEnum $permission, $scope = null): void
    {
        $this->permissions()
            ->where('permission', $permission instanceof BackedEnum ? $permission->value : $permission)
            ->when($scope, fn ($q) => $q->whereMorphedTo('scope', $scope))
            ->delete();
    }

    public function hasPermission(string|BackedEnum $permission, $scope = null): bool
    {
        return $this->hasPermissions($permission, $scope);
    }

    public function hasPermissions(string|BackedEnum|array $permissions, $scope = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $permissionValues = [];
        foreach ($permissions as $permission) {
            $permissionValues[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $permissionValues = array_unique($permissionValues);

        $count = $this->permissions()
            ->whereIn('permission', $permissionValues)
            ->when($scope, fn ($q) => $q->whereMorphedTo('scope', $scope))
            ->count();

        return $count === count($permissionValues);
    }

    /**
     * Synchronize the actor's permissions for a given scope to match the provided permission handles.
     * 
     * @param string|BackedEnum|array<string|BackedEnum> $permissionHandles
     * @param Model|null $scope
     * @return void
     */
    public function syncPermissions(string|BackedEnum|array $permissionHandles, $scope = null): void
    {
        $handles = is_array($permissionHandles) ? $permissionHandles : [$permissionHandles];

        $permissionValues = [];
        foreach ($handles as $permission) {
            $permissionValues[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $permissionValues = array_unique($permissionValues);

        $currentPermissions = $this->permissions()
            ->whereMorphedTo('scope', $scope)
            ->pluck('permission')
            ->toArray();

        $toRemove = array_diff($currentPermissions, $permissionValues);
        $toAdd = array_diff($permissionValues, $currentPermissions);

        if (!empty($toRemove)) {
            $this->permissions()
                ->whereMorphedTo('scope', $scope)
                ->whereIn('permission', $toRemove)
                ->delete();
        }

        foreach ($toAdd as $permissionHandle) {
            $this->allow($permissionHandle, $scope);
        }
    }
}
