<?php

namespace EduLazaro\Larallow\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use EduLazaro\Larallow\LarallowServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        // Testbench does not guarantee a session driver across versions, and
        // auth()->user() boots the session guard. Without this, any test that
        // touches the guard dies with "Driver [] not supported" on Laravel 11+.
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/migrations');

        $this->artisan('migrate')->run();
    }

    protected function getPackageProviders($app)
    {
        return [
            LarallowServiceProvider::class,
        ];
    }
}
