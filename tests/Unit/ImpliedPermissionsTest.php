<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Permission;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Enums\UserPermissions;

class ImpliedPermissionsTest extends TestCase
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
    }

    /** @test */
    public function user_has_permission_if_implied_by_assigned_permission()
    {
        $user = User::create();

        $user->allow(UserPermissions::ManagePosts);

        $this->assertFalse($user->hasPermission(UserPermissions::DeletePost));
        $this->assertTrue(
            Permissions::query()
                ->permissions(UserPermissions::ViewPost)
                ->for($user)
                ->check()
        );
    }

    /** @test */
    public function user_does_not_have_implied_permission_if_not_configured()
    {
        $user = User::create();

        $user->allow(UserPermissions::ViewPost);

        $this->assertFalse(
            Permissions::query()
                ->permissions(UserPermissions::EditPost)
                ->for($user)
                ->check()
        );
    }

    /** @test */
    public function user_must_have_explicit_or_implied_permission()
    {
        $user = User::create();

        $this->assertFalse(
            Permissions::query()
                ->permissions(UserPermissions::ViewPost)
                ->for($user)
                ->check()
        );

        $user->allow(UserPermissions::ManagePosts);

        $this->assertTrue(
            Permissions::query()
                ->permissions(UserPermissions::ViewPost)
                ->for($user)
                ->check()
        );
    }

    /** @test */
    public function user_must_have_all_explicit_or_implied_permissions_for_check_all()
    {
        $user = User::create();

        $user->allow(UserPermissions::ManagePosts); // implies ViewPost

        $this->assertFalse(
            Permissions::query()
                ->permissions([UserPermissions::ViewPost, UserPermissions::EditPost])
                ->for($user)
                ->checkAll() // missing EditPost → false
        );

        $user->allow(UserPermissions::EditPost); // now has both

        $this->assertTrue(
            Permissions::query()
                ->permissions([UserPermissions::ViewPost, UserPermissions::EditPost])
                ->for($user)
                ->checkAll()
        );
    }
}
