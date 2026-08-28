<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Permission;
use EduLazaro\Larallow\Tests\Support\Models\Account;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\TestCase;

/**
 * Conceder dos veces el mismo permiso deja una fila, no dos.
 *
 * El indice unique_actor_permission no basta: incluye scope_type y scope_id, que admiten
 * nulos, y en SQL un nulo no es igual a otro nulo. Asi que una concesion global repetida
 * atraviesa el indice sin error y duplica en silencio. Con scope si lo frena, o sea que
 * sin esto el mismo codigo duplica o revienta segun le pases scope.
 */
class AllowIsIdempotentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['edit-post' => 'Edit Post'])->for(User::class);
        Permission::create(['manage-account' => 'Manage Account'])->for(User::class)->on(Account::class);
    }

    public function test_granting_the_same_global_permission_twice_leaves_one_row(): void
    {
        $user = User::create();

        $user->allow('edit-post');
        $user->allow('edit-post');

        $this->assertSame(1, $user->permissions()->where('permission', 'edit-post')->count());
    }

    public function test_granting_the_same_scoped_permission_twice_does_not_throw(): void
    {
        $user = User::create();
        $account = Account::create();

        $user->allow('manage-account', $account);
        $user->allow('manage-account', $account);

        $this->assertSame(1, $user->permissions()->where('permission', 'manage-account')->count());
    }

    public function test_both_forms_behave_the_same(): void
    {
        $user = User::create();

        $user->allow('edit-post');
        $user->permissions('edit-post')->allow();

        $this->assertSame(1, $user->permissions()->where('permission', 'edit-post')->count());
    }
}
