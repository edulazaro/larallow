<?php

namespace EduLazaro\Larallow\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use EduLazaro\Larallow\LarallowServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
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
