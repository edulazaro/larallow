<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Permission;

use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Models\Client;

use EduLazaro\Larallow\Tests\Support\Enums\UserPermissions;
use EduLazaro\Larallow\Tests\Support\Enums\ClientPermissions;
use EduLazaro\Larallow\Models\Role;

class RolesAndPermissionsTest extends TestCase
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

    protected function createUserWithPermissions(array $directPermissions = [], array $rolePermissions = [], $scope = null): User
    {
        $user = new User();
        $user->save();

        foreach ($directPermissions as $permission) {
            $user->permissions()->create(['permission' => $permission]);
        }

        if (!empty($rolePermissions)) {
            $roleData = [
                'handle' => 'test-role',
                'scope_type' => $scope?->getMorphClass(),
                'actor_type'    => $user?->getMorphClass(),
                'translations' => json_encode([]),
            ];
            $role = Role::create($roleData);

            foreach ($rolePermissions as $permission) {
                $role->permissions()->create(['permission' => $permission]);
            }

            if ($scope) {
                $user->assignRole($role, $scope);
            } else {
                $user->assignRole($role);
            }
        }

        $user->load('permissions', 'roles.permissions');
        return $user;
    }

    /** @test */
    public function check_returns_true_for_user_with_all_direct_permissions()
    {
        $user = $this->createUserWithPermissions([
            UserPermissions::EditPost->value,
            UserPermissions::ViewDashboard->value,
        ]);

        $result = Permissions::query()
            ->permissions([
                UserPermissions::EditPost,
                UserPermissions::ViewDashboard,
            ])
            ->for($user)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */
    public function check_returns_true_for_user_with_all_permissions_via_roles()
    {
        $user = $this->createUserWithPermissions([], [
            UserPermissions::EditPost->value,
            UserPermissions::ViewDashboard->value,
        ]);

        $result = Permissions::query()
            ->permissions([
                UserPermissions::EditPost,
                UserPermissions::ViewDashboard,
            ])
            ->for($user)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */
    public function check_returns_true_for_user_with_permissions_combined_direct_and_roles()
    {
        $user = $this->createUserWithPermissions(
            [UserPermissions::EditPost->value],
            [UserPermissions::ViewDashboard->value]
        );

        $result = Permissions::query()
            ->permissions([
                UserPermissions::EditPost,
                UserPermissions::ViewDashboard,
            ])
            ->for($user)
            ->check();

        $this->assertTrue($result);
    }

   /** @test */
    public function check_returns_true_for_user_missing_any_permission()
    {
        $user = $this->createUserWithPermissions([UserPermissions::EditPost->value]);

        $result = Permissions::query()
            ->permissions([
                UserPermissions::EditPost,
                UserPermissions::ViewDashboard,
            ])
            ->for($user)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */
    public function check_returns_false_for_user_missing_all_permission()
    {
        $user = $this->createUserWithPermissions([UserPermissions::EditPost->value]);

        $result = Permissions::query()
            ->permissions([
                UserPermissions::EditPost,
                UserPermissions::ViewDashboard,
            ])
            ->for($user)
            ->checkAll();

        $this->assertFalse($result);
    }

    /** @test */
    public function check_returns_true_for_user_with_scope_scope()
    {
        $scope = new class {
            public function getMorphClass() { return 'app-scope'; }
            public function getKey() { return 1; }
        };

        $user = $this->createUserWithPermissions(
            [],
            [UserPermissions::EditPost->value],
            $scope
        );

        $result = Permissions::query()
            ->permissions([UserPermissions::EditPost])
            ->for($user)
            ->on($scope)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */
    public function check_returns_false_for_user_with_wrong_scope_scope()
    {
        $correctRoleable = new class {
            public function getMorphClass() { return 'app-scope'; }
            public function getKey() { return 1; }
        };

        $wrongRoleable = new class {
            public function getMorphClass() { return 'app-scope'; }
            public function getKey() { return 2; }
        };

        $user = $this->createUserWithPermissions(
            [],
            [UserPermissions::EditPost->value],
            $correctRoleable
        );

        $result = Permissions::query()
            ->permissions([UserPermissions::EditPost])
            ->for($user)
            ->on($wrongRoleable)
            ->check();

        $this->assertFalse($result);
    }

    /** @test */
    public function check_returns_true_for_client_with_direct_permissions()
    {
        $client = new Client();
        $client->save();

        $client->allow(ClientPermissions::ViewAccount);

        $result = Permissions::query()
            ->permissions(ClientPermissions::ViewAccount)
            ->for($client)
            ->check();

        $this->assertTrue($result);
    }

    /** @test */
    public function check_returns_false_for_client_missing_permissions()
    {
        $client = new Client();
        $client->save();

        $result = Permissions::query()
            ->permissions(ClientPermissions::MakePayment)
            ->for($client)
            ->check();

        $this->assertFalse($result);
    }
}
