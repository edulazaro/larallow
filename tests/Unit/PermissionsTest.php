<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Blade;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Permission;

use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Models\Client;

use EduLazaro\Larallow\Tests\Support\Enums\UserPermissions;
use EduLazaro\Larallow\Tests\Support\Enums\ClientPermissions;

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

}
