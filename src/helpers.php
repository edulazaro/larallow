<?php

use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Roles;

if (!function_exists('permissions')) {
    function permissions(string|BackedEnum|array $permissions): Permissions
    {
        return Permissions::query()->permissions($permissions);
    }
}

if (!function_exists('roles')) {
    /**
     * Helper para crear una instancia de Roles y trabajar con ella.
     *
     * @param int|string|array<int|string|Role> $roles
     * @return Roles
     */
    function roles(int|string|array $roles): Roles
    {
        return Roles::query()->roles($roles);
    }
}