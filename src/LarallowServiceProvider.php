<?php

namespace EduLazaro\Larallow;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Roles;
use BackedEnum;

class LarallowServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'larallow');

        $this->loadDirectives();
        $this->loadHelpers();
    }

    /**
     * Register Blade directives
     *
     * @return void
     */
    protected function loadDirectives()
    {
        Blade::if('permissions', function (string|BackedEnum|array $permissions, $permissionable = null) {
            return Permissions::query()->permissions($permissions)
                ->on($permissionable)
                ->check();
        });

        Blade::if('roles', function (string|array $roles) {
            return Roles::query()
                ->roles($roles)
                ->for(auth()->user())
                ->check();
        });
    }

    /**
     * Register the helper functions.
     *
     * @return void
     */
    protected function loadHelpers()
    {
        require_once __DIR__ . '/helpers.php';
    }
}
