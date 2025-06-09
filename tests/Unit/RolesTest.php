<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use EduLazaro\Larallow\Models\Role;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Models\Client;
use EduLazaro\Larallow\Permission;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create('manage-posts')->label('Manage Posts')->for(User::class)->implies('view-post');

        Permission::create([
            'edit-post' => 'Edit Post',
            'view-post' => 'View Post',
            'delete-post' => 'Delete Post',
            'view-dashboard' => 'View Dashboard',
        ])->for(User::class);

        Permission::create([
            'view-account' => 'View Account',
            'make-payment' => 'Make Payment',
        ])->for(Client::class);
    }

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

    /** @test */
    public function permissions_check_returns_true_if_user_has_any_of_the_permissions()
    {
        $user = new User();
        $user->save();

        $role = $this->createRole('editor');
        $role->permissions()->create(['permission' => 'edit-post']);
        $user->assignRole($role);

        $result = $user->permissions(['edit-post', 'delete-post'])
            ->for($user)
            ->check();

        $this->assertTrue($result); // Has 'edit-post' via role
    }

    /** @test */
    public function permissions_check_all_returns_false_if_user_lacks_any_permission()
    {
        $user = new User();
        $user->save();

        $role = $this->createRole('editor');
        $role->permissions()->create(['permission' => 'edit-post']);
        $user->assignRole($role);

        $result = $user->permissions(['edit-post', 'delete-post'])
            ->for($user)
            ->checkAll();

        $this->assertFalse($result); // Missing 'delete-post'
    }
}
