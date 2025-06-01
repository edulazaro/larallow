<?php

namespace EduLazaro\Larallow\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use EduLazaro\Larallow\Models\ActorPermission;
use EduLazaro\Larallow\Permissions;
use InvalidArgumentException;
use BackedEnum;

trait HasPermissions
{
    public static $allowedPermissions = [];

    public static function allowed(string|array $permissionsEnumClasses): void
    {
        $permissions = is_array($permissionsEnumClasses) ? $permissionsEnumClasses : [$permissionsEnumClasses];

        $allPermissions = [];

        if (!empty(static::$allowedPermissions)) {
            $allPermissions = static::$allowedPermissions;
        }

        foreach ($permissions as $perm) {
            if (enum_exists($perm)) {
                foreach ($perm::cases() as $case) {
                    $allPermissions[] = $case->value;
                }
            } else {
                $allPermissions[] = $perm;
            }
        }

        static::$allowedPermissions = array_unique($allPermissions);
    }

    public function permissions(string|array|null $permissions = null): MorphMany|Permissions
    {
        if (is_null($permissions)) {
            return $this->morphMany(ActorPermission::class, 'actor');
        }

        return Permissions::query()->permissions($permissions)->for($this);
    }

    public function allow(string|BackedEnum $permission, $permissionable = null): void
    {
        $permissionValue = $permission instanceof BackedEnum ? $permission->value : $permission;

        if (!in_array($permissionValue, static::$allowedPermissions, true)) {
            throw new InvalidArgumentException("Permission '{$permissionValue}' is not allowed.");
        }

        $this->permissions()->create([
            'permission' => $permissionValue,
            'permissionable_type' => optional($permissionable)->getMorphClass(),
            'permissionable_id' => optional($permissionable)->getKey(),
        ]);
    }

    public function deny(string|BackedEnum $permission, $permissionable = null): void
    {
        $this->permissions()
            ->where('permission', $permission instanceof BackedEnum ? $permission->value : $permission)
            ->when($permissionable, fn ($q) => $q->whereMorphedTo('permissionable', $permissionable))
            ->delete();
    }

    public function hasPermission(string|BackedEnum $permission, $permissionable = null): bool
    {
        return $this->hasPermissions($permission, $permissionable);
    }

    public function hasPermissions(string|BackedEnum|array $permissions, $permissionable = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $permissionValues = [];
        foreach ($permissions as $permission) {
            $permissionValues[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $permissionValues = array_unique($permissionValues);

        $count = $this->permissions()
            ->whereIn('permission', $permissionValues)
            ->when($permissionable, fn ($q) => $q->whereMorphedTo('permissionable', $permissionable))
            ->count();

        return $count === count($permissionValues);
    }
}
