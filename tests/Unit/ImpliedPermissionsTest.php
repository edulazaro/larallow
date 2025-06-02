<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use EduLazaro\Larallow\Permissions;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\Support\Enums\UserPermissions;

class ImpliedPermissionsTest extends TestCase
{
    /** @test */
    public function user_has_permission_if_implied_by_assigned_permission()
    {
        User::allowed(UserPermissions::class);

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
        User::allowed(UserPermissions::class);

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
        User::allowed(UserPermissions::class);

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
}
