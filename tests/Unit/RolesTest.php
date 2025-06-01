<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use EduLazaro\Larallow\Models\Role;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Models\Client;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function createRole(string $handle): Role
    {
        return Role::create(['handle' => $handle]);
    }

    /** @test */
    public function user_can_assign_and_remove_roles()
    {
        $user = new User();
        $user->save();

        $role = $this->createRole('admin');

        $user->assignRole($role);
        $this->assertTrue($user->hasRole('admin'));

        $user->removeRole($role);
        $this->assertFalse($user->hasRole('admin'));
    }

    /** @test */
    public function client_can_assign_and_remove_roles()
    {
        $client = new Client();
        $client->save();

        $role = $this->createRole('customer');

        $client->assignRole($role);
        $this->assertTrue($client->hasRole('customer'));

        $client->removeRole($role);
        $this->assertFalse($client->hasRole('customer'));
    }

    /** @test */
    public function user_has_role_permission_checks_permission_on_roles()
    {
        $user = new User();
        $user->save();

        $role = $this->createRole('editor');
        $role->permissions()->create(['permission' => 'edit-post']);

        $user->assignRole($role);

        $this->assertTrue($user->hasRolePermission('edit-post'));
        $this->assertFalse($user->hasRolePermission('delete-post'));
    }

    /** @test */
    public function client_has_role_permission_checks_permission_on_roles()
    {
        $client = new Client();
        $client->save();

        $role = $this->createRole('vip');
        $role->permissions()->create(['permission' => 'make-payment']);

        $client->assignRole($role);

        $this->assertTrue($client->hasRolePermission('make-payment'));
        $this->assertFalse($client->hasRolePermission('cancel-order'));
    }

    /** @test */
    public function user_has_role_permissions_accepts_array()
    {
        $user = new User();
        $user->save();

        $role = $this->createRole('manager');
        $role->permissions()->create(['permission' => 'edit-post']);
        $role->permissions()->create(['permission' => 'delete-post']);

        $user->assignRole($role);

        $this->assertTrue($user->hasRolePermissions(['edit-post', 'delete-post']));
        $this->assertFalse($user->hasRolePermissions(['edit-post', 'non-existent-permission']));
    }

    /** @test */
    public function hasAnyRolePermission_returns_true_if_any_permission_exists()
    {
        $user = new User();
        $user->save();

        $role = $this->createRole('editor');
        $role->permissions()->create(['permission' => 'edit-post']);
        $role->permissions()->create(['permission' => 'delete-post']);

        $user->assignRole($role);

        $this->assertTrue($user->hasAnyRolePermission('edit-post'));
        $this->assertTrue($user->hasAnyRolePermissions(['edit-post', 'non-existent-permission']));
        $this->assertTrue($user->hasAnyRolePermission('edit-post'));
        $this->assertFalse($user->hasAnyRolePermission('non-existent-permission'));
    }
}
