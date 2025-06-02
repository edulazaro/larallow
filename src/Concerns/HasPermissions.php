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
    public static array $impliedPermissions = [];

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

                    if (method_exists($case, 'implied')) {
                        foreach ($case->implied() as $impliedCase) {
                            static::$impliedPermissions[$case->value][] = $impliedCase instanceof BackedEnum
                                ? $impliedCase->value
                                : (string) $impliedCase;
                        }
                    }
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

    public function allow(string|BackedEnum $permission, $scopable = null): void
    {
        $permissionValue = $permission instanceof BackedEnum ? $permission->value : $permission;

        if (!in_array($permissionValue, static::$allowedPermissions, true)) {
            throw new InvalidArgumentException("Permission '{$permissionValue}' is not allowed.");
        }

        $this->permissions()->create([
            'permission' => $permissionValue,
            'scopable_type' => optional($scopable)->getMorphClass(),
            'scopable_id' => optional($scopable)->getKey(),
        ]);
    }

    public function deny(string|BackedEnum $permission, $scopable = null): void
    {
        $this->permissions()
            ->where('permission', $permission instanceof BackedEnum ? $permission->value : $permission)
            ->when($scopable, fn ($q) => $q->whereMorphedTo('scopable', $scopable))
            ->delete();
    }

    public function hasPermission(string|BackedEnum $permission, $scopable = null): bool
    {
        return $this->hasPermissions($permission, $scopable);
    }

    public function hasPermissions(string|BackedEnum|array $permissions, $scopable = null): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $permissionValues = [];
        foreach ($permissions as $permission) {
            $permissionValues[] = $permission instanceof BackedEnum ? $permission->value : $permission;
        }

        $permissionValues = array_unique($permissionValues);

        $count = $this->permissions()
            ->whereIn('permission', $permissionValues)
            ->when($scopable, fn ($q) => $q->whereMorphedTo('scopable', $scopable))
            ->count();

        return $count === count($permissionValues);
    }
}
