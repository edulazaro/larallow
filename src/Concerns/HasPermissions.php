<?php

namespace EduLazaro\Larallow\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use EduLazaro\Larallow\Models\ActorPermission;
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

    public function allow(string|BackedEnum $permission, $scope = null): void
    {
        $permissionValue = $permission instanceof BackedEnum ? $permission->value : $permission;

        $actorType = $this->getMorphClass();
        $scopeType = $scope?->getMorphClass();

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
}
