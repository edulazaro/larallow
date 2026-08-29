<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Permission;

use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Models\Client;
use EduLazaro\Larallow\Tests\Support\Models\Account;

use EduLazaro\Larallow\Tests\Support\Enums\UserPermissions;
use EduLazaro\Larallow\Tests\Support\Enums\ClientPermissions;
use EduLazaro\Larallow\Models\Role;

class PermissionsTest extends TestCase
{
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

        Permission::create('manage-account')
            ->label('Manage Account')
            ->for(User::class)
            ->on(Account::class);
    }

    /** @test */
    public function migrations_are_loaded_and_table_exists()
    {
        $this->assertTrue(Schema::hasTable('actor_permissions'));
    }

    public function user_can_use_registered_permissions()
    {
        $user = User::create();

        $user->allow(UserPermissions::EditPost);

        $this->assertTrue($user->hasPermission(UserPermissions::EditPost));
    }

    public function client_can_use_registered_permissions()
    {
        $client = Client::create();

        $client->allow(ClientPermissions::ViewAccount);

        $this->assertTrue($client->hasPermission(ClientPermissions::ViewAccount));
    }

    /** @test */
    public function can_create_permission_for_user_using_enum()
    {
        $user = new User();
        $user->save();

        $user->allow(UserPermissions::EditPost);

        $this->assertDatabaseHas('actor_permissions', [
            'actor_type' => User::class,
            'actor_id' => $user->id,
            'permission' => UserPermissions::EditPost->value,
        ]);
    }

    /** @test */
    public function permissions_check_works_with_enum()
    {
        $user = new User();
        $user->save();

        $user->allow(UserPermissions::EditPost);

        $this->assertTrue($user->hasPermission(UserPermissions::EditPost));
        $this->assertFalse($user->hasPermission(UserPermissions::DeletePost));
    }

    /** @test */
    public function permissions_check_works_with_string()
    {
        $user = new User();
        $user->save();

        $user->allow('edit-post');

        $this->assertTrue($user->hasPermission('edit-post'));
        $this->assertFalse($user->hasPermission('delete-post'));
    }

    /** @test */
    public function permissions_class_check_method_returns_true_if_allowed()
    {
        $user = new User();
        $user->save();

        $user->allow(UserPermissions::EditPost);

        $result = Permissions::query()
            ->permissions(UserPermissions::EditPost)
            ->for($user)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */

    public function blade_directive_returns_true_for_allowed_permission()
    {
        $user = new User();
        $user->save();

        auth()->login($user);

        $user->allow(UserPermissions::EditPost);

        $blade = "@permissions('edit-post')Allowed @endpermissions";

        $rendered = Blade::render($blade);

        $this->assertStringContainsString('Allowed', $rendered);
    }

    /** @test */
    public function permissions_check_returns_true_if_any_permission_is_granted()
    {
        $user = new User();
        $user->save();

        $user->allow(UserPermissions::EditPost);

        $result = Permissions::query()
            ->permissions([UserPermissions::EditPost, UserPermissions::DeletePost])
            ->for($user)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */
    public function permissions_check_all_returns_true_only_if_all_permissions_are_granted()
    {
        $user = new User();
        $user->save();

        $user->allow(UserPermissions::EditPost);

        $this->assertFalse(
            $user->permissions([UserPermissions::EditPost, UserPermissions::DeletePost])
                ->for($user)
                ->checkAll()
        );

        $user->allow(UserPermissions::DeletePost);

        $this->assertTrue(
            $user->permissions([UserPermissions::EditPost, UserPermissions::DeletePost])
                ->for($user)
                ->checkAll()
        );
    }

    /** @test */
    public function user_can_have_scoped_permission()
    {
        $user = User::create();
        $account = Account::create();

        $user->allow('manage-account', $account);

        $this->assertTrue($user->hasPermission('manage-account', $account));
        $this->assertFalse($user->hasPermission('manage-account')); // without scope
    }

    /** @test */
    public function scoped_permission_does_not_grant_global_permission()
    {
        $user = User::create();
        $account = Account::create();

        $user->allow('manage-account', $account);

        $this->assertFalse($user->hasPermission('manage-account'));
    }

    /** @test */
    public function scoped_permission_is_specific_to_scope_instance()
    {
        $user = User::create();
        $account1 = Account::create();
        $account2 = Account::create();

        $user->allow('manage-account', $account1);

        $this->assertTrue($user->hasPermission('manage-account', $account1));
        $this->assertFalse($user->hasPermission('manage-account', $account2));
    }

    /** @test */
    public function user_can_have_permission_on_multiple_scopes()
    {
        $user = User::create();
        $account1 = Account::create();
        $account2 = Account::create();

        $user->allow('manage-account', $account1);
        $user->allow('manage-account', $account2);

        $this->assertTrue($user->hasPermission('manage-account', $account1));
        $this->assertTrue($user->hasPermission('manage-account', $account2));
    }

    /** @test */
    public function scoped_permission_is_stored_in_database()
    {
        $user = User::create();
        $account = Account::create();

        $user->allow('manage-account', $account);

        $this->assertDatabaseHas('actor_permissions', [
            'actor_type' => User::class,
            'actor_id' => $user->id,
            'permission' => 'manage-account',
            'scope_type' => Account::class,
            'scope_id' => $account->id,
        ]);
    }

    /** @test */
    public function deny_removes_scoped_permission()
    {
        $user = User::create();
        $account = Account::create();

        $user->allow('manage-account', $account);
        $this->assertTrue($user->hasPermission('manage-account', $account));

        $user->deny('manage-account', $account);
        $this->assertFalse($user->hasPermission('manage-account', $account));
    }

    /** @test */
    public function deny_scoped_permission_does_not_affect_other_scopes()
    {
        $user = User::create();
        $account1 = Account::create();
        $account2 = Account::create();

        $user->allow('manage-account', $account1);
        $user->allow('manage-account', $account2);

        $user->deny('manage-account', $account1);

        $this->assertFalse($user->hasPermission('manage-account', $account1));
        $this->assertTrue($user->hasPermission('manage-account', $account2));
    }

    // ========================================
    // Query Scope Tests
    // ========================================

    /** @test */
    public function withPermission_finds_users_with_direct_permission()
    {
        $user1 = User::create();
        $user2 = User::create();
        $user3 = User::create();

        $user1->allow('edit-post');
        $user2->allow('delete-post');
        // user3 has no permissions

        $usersWithEditPost = User::withPermission('edit-post')->get();

        $this->assertTrue($usersWithEditPost->contains($user1));
        $this->assertFalse($usersWithEditPost->contains($user2));
        $this->assertFalse($usersWithEditPost->contains($user3));
    }

    /** @test */
    public function withPermission_finds_users_with_permission_via_role()
    {
        $user1 = User::create();
        $user2 = User::create();

        $role = Role::create(['handle' => 'editor']);
        $role->permissions()->create(['permission' => 'edit-post']);

        $user1->assignRole($role);
        // user2 has no role

        $usersWithEditPost = User::withPermission('edit-post')->get();

        $this->assertTrue($usersWithEditPost->contains($user1));
        $this->assertFalse($usersWithEditPost->contains($user2));
    }

    /** @test */
    public function withPermission_finds_users_with_implied_permission()
    {
        $user = User::create();

        // manage-posts implies view-post (set up in setUp())
        $user->allow('manage-posts');

        $usersWithViewPost = User::withPermission('view-post')->get();

        $this->assertTrue($usersWithViewPost->contains($user));
    }

    /** @test */
    public function withPermission_respects_scope()
    {
        $user1 = User::create();
        $user2 = User::create();
        $account = Account::create();

        $user1->allow('manage-account', $account);
        $user2->allow('manage-account'); // global, not scoped

        // Note: withPermission with scope checks for scoped permissions
        // This requires the permission to be scoped to the specific account
        $usersWithScopedPermission = User::withPermission('manage-account', $account)->get();

        $this->assertTrue($usersWithScopedPermission->contains($user1));
    }

    /** @test */
    public function withAnyPermission_finds_users_with_any_of_the_permissions()
    {
        $user1 = User::create();
        $user2 = User::create();
        $user3 = User::create();

        $user1->allow('edit-post');
        $user2->allow('delete-post');
        // user3 has no permissions

        $users = User::withAnyPermission(['edit-post', 'delete-post'])->get();

        $this->assertTrue($users->contains($user1));
        $this->assertTrue($users->contains($user2));
        $this->assertFalse($users->contains($user3));
    }

    /** @test */
    public function withAnyPermission_returns_empty_if_no_users_have_permissions()
    {
        $user = User::create();
        // user has no permissions

        $users = User::withAnyPermission(['edit-post', 'delete-post'])->get();

        $this->assertFalse($users->contains($user));
    }

    /** @test */
    public function withAllPermissions_finds_users_with_all_permissions()
    {
        $user1 = User::create();
        $user2 = User::create();
        $user3 = User::create();

        $user1->allow('edit-post');
        $user1->allow('delete-post');

        $user2->allow('edit-post');
        // user2 missing delete-post

        // user3 has no permissions

        $users = User::withAllPermissions(['edit-post', 'delete-post'])->get();

        $this->assertTrue($users->contains($user1));
        $this->assertFalse($users->contains($user2));
        $this->assertFalse($users->contains($user3));
    }

    /** @test */
    public function withAllPermissions_works_with_mixed_direct_and_role_permissions()
    {
        $user = User::create();

        // Direct permission
        $user->allow('edit-post');

        // Permission via role
        $role = Role::create(['handle' => 'moderator']);
        $role->permissions()->create(['permission' => 'delete-post']);
        $user->assignRole($role);

        $users = User::withAllPermissions(['edit-post', 'delete-post'])->get();

        $this->assertTrue($users->contains($user));
    }

}
