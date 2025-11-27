<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use EduLazaro\Larallow\Models\Role;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Models\Client;
use EduLazaro\Larallow\Tests\Support\Models\Account;
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

    /** @test */
    public function syncRoles_adds_new_roles()
    {
        $user = new User();
        $user->save();

        $role1 = $this->createRole('admin');
        $role2 = $this->createRole('editor');

        $user->syncRoles([$role1, $role2]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    /** @test */
    public function syncRoles_removes_roles_not_in_list()
    {
        $user = new User();
        $user->save();

        $role1 = $this->createRole('admin');
        $role2 = $this->createRole('editor');
        $role3 = $this->createRole('viewer');

        $user->assignRoles([$role1, $role2, $role3]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertTrue($user->hasRole('viewer'));

        // Sync with only admin and editor - viewer should be removed
        $user->syncRoles([$role1, $role2]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
        $this->assertFalse($user->hasRole('viewer'));
    }

    /** @test */
    public function syncRoles_keeps_existing_roles_in_list()
    {
        $user = new User();
        $user->save();

        $role1 = $this->createRole('admin');
        $role2 = $this->createRole('editor');

        $user->assignRole($role1);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('editor'));

        // Sync with both - admin should stay, editor should be added
        $user->syncRoles([$role1, $role2]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    /** @test */
    public function user_can_have_scoped_role()
    {
        $user = User::create();
        $account = Account::create();

        $role = $this->createRole('editor');
        $user->assignRole($role, $account);

        // Check the pivot data is stored correctly
        $this->assertDatabaseHas('actor_role', [
            'actor_type' => User::class,
            'actor_id' => $user->id,
            'role_id' => $role->id,
            'scope_type' => Account::class,
            'scope_id' => $account->id,
        ]);

        // Reload to get fresh pivot data
        $user->load('roles');

        $this->assertTrue($user->hasRole('editor', $account));
        $this->assertFalse($user->hasRole('editor')); // without scope
    }

    /** @test */
    public function syncRoles_with_scope_only_affects_that_scope()
    {
        $user = User::create();
        $account = Account::create();

        $role1 = $this->createRole('admin');
        $role2 = $this->createRole('editor');
        $role3 = $this->createRole('manager');

        // Assign role1 globally (no scope)
        $user->assignRole($role1);
        // Assign role2 with scope
        $user->assignRole($role2, $account);

        $user->load('roles'); // Ensure roles are loaded with pivot

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor', $account));

        // Sync only scoped roles to role3
        $user->syncRoles([$role3], $account);

        // Global role should remain
        $this->assertTrue($user->hasRole('admin'));
        // Scoped role2 should be removed
        $this->assertFalse($user->hasRole('editor', $account));
        // role3 should be added with scope
        $this->assertTrue($user->hasRole('manager', $account));
    }

    /** @test */
    public function syncRoles_accepts_role_ids_and_role_objects()
    {
        $user = new User();
        $user->save();

        $role1 = $this->createRole('admin');
        $role2 = $this->createRole('editor');

        // Mix of Role object and ID
        $user->syncRoles([$role1, $role2->id]);

        $this->assertTrue($user->hasRole('admin'));
        $this->assertTrue($user->hasRole('editor'));
    }

    /** @test */
    public function role_can_add_single_permission()
    {
        $role = $this->createRole('editor');

        $role->addPermission('edit-post');

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'edit-post',
        ]);
    }

    /** @test */
    public function role_can_add_multiple_permissions()
    {
        $role = $this->createRole('editor');

        $role->addPermission(['edit-post', 'delete-post', 'view-post']);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'edit-post',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'delete-post',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'view-post',
        ]);
    }

    /** @test */
    public function role_addPermission_does_not_duplicate()
    {
        $role = $this->createRole('editor');

        $role->addPermission('edit-post');
        $role->addPermission('edit-post');

        $this->assertEquals(1, $role->permissions()->where('permission', 'edit-post')->count());
    }

    /** @test */
    public function role_can_remove_single_permission()
    {
        $role = $this->createRole('editor');
        $role->addPermission(['edit-post', 'delete-post']);

        $role->removePermission('edit-post');

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'edit-post',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'delete-post',
        ]);
    }

    /** @test */
    public function role_can_remove_multiple_permissions()
    {
        $role = $this->createRole('editor');
        $role->addPermission(['edit-post', 'delete-post', 'view-post']);

        $role->removePermission(['edit-post', 'delete-post']);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'edit-post',
        ]);
        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'delete-post',
        ]);
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission' => 'view-post',
        ]);
    }
}
