<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Roles;
use EduLazaro\Larallow\Tests\TestCase;
use Mockery;

/**
 * Las directivas Blade con un visitante sin autenticar.
 *
 * auth()->user() es null, y method_exists(null, ...) es un TypeError desde PHP 8, asi
 * que un @permissions en una pagina publica tumbaba la pagina entera. Las de roles ya
 * salian bien porque Roles::check() comprueba el actor antes de nada.
 */
class GuestDirectivesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Un visitante sin autenticar, sin arrancar el gestor de sesion de Laravel.
        // auth()->user() resuelve el guard 'web', que arranca la sesion, y en PHP 8.6
        // instanciar ArraySessionHandler emite una deprecacion que Laravel convierte en
        // excepcion: un aviso del framework tumbaba un test que no va de eso. Lo que
        // aqui se prueba es que un actor nulo devuelva false, no el stack de auth.
        $this->app->instance('auth', Mockery::mock(['user' => null]));
    }

    public function test_permissions_returns_false_for_a_guest_instead_of_throwing(): void
    {
        $this->assertFalse(Permissions::query()->permissions('edit-post')->on(null)->check());
    }

    public function test_all_permissions_returns_false_for_a_guest_instead_of_throwing(): void
    {
        $this->assertFalse(Permissions::query()->permissions('edit-post')->on(null)->checkAll());
    }

    public function test_roles_already_returned_false_for_a_guest(): void
    {
        $this->assertFalse(Roles::query()->roles([1])->for(null)->check());
        $this->assertFalse(Roles::query()->roles([1])->for(null)->checkAll());
    }
}
