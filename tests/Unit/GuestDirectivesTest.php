<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Roles;
use EduLazaro\Larallow\Tests\TestCase;

/**
 * Las directivas Blade con un visitante sin autenticar.
 *
 * auth()->user() es null, y method_exists(null, ...) es un TypeError desde PHP 8, asi
 * que un @permissions en una pagina publica tumbaba la pagina entera. Las de roles ya
 * salian bien porque Roles::check() comprueba el actor antes de nada.
 */
class GuestDirectivesTest extends TestCase
{
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
