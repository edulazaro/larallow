<?php

namespace EduLazaro\Larallow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use EduLazaro\Larallow\Permission;

class PermissionTest extends TestCase
{
    protected function setUp(): void
    {
        Permission::$registry = [];
        Permission::$impliedPermissions = [];
    }

    public function testCreateSinglePermission()
    {
        $perm = Permission::create('edit-post');
        $this->assertInstanceOf(Permission::class, $perm);
        $this->assertEquals('edit-post', $perm->handle);
        $this->assertTrue(Permission::exists('edit-post'));
    }

    public function testIsAllowedForWithActorAndScope()
    {
        $perm = Permission::create('edit-post')->for('user')->on('group');
        $this->assertTrue(Permission::isAllowedFor('edit-post', 'user', 'group'));
        $this->assertFalse(Permission::isAllowedFor('edit-post', 'admin', 'group'));
        $this->assertFalse(Permission::isAllowedFor('edit-post', 'user', 'other-scope'));
    }

    public function testQueryBuilderFiltering()
    {
        Permission::create([
            'edit-post' => 'Edit Post',
            'delete-post' => 'Delete Post',
            'view-post' => 'View Post',
        ]);

        Permission::get('edit-post')->for('user')->on('group');
        Permission::get('delete-post')->for('user')->on('group');
        Permission::get('view-post')->for('admin')->on('group');

        $results = Permission::query()
            ->where('actor_type', 'user')
            ->where('scope_type', 'group')
            ->get();

        $handles = array_map(fn($p) => $p->handle, $results);
        $this->assertContains('edit-post', $handles);
        $this->assertContains('delete-post', $handles);
        $this->assertNotContains('view-post', $handles);

        $first = Permission::where('handle', 'edit-post')->first();
        $this->assertEquals('edit-post', $first->handle);
    }

    public function testImpliedPermissionsTransitively()
    {
        Permission::create('manage-posts')->implies('edit-post');
        Permission::create('edit-post')->implies('view-post');

        $implied = Permission::$impliedPermissions;

        $this->assertArrayHasKey('manage-posts', $implied);
        $this->assertContains('edit-post', $implied['manage-posts']);

        $this->assertArrayHasKey('edit-post', $implied);
        $this->assertContains('view-post', $implied['edit-post']);
    }
}
