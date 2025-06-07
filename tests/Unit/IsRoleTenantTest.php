<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use EduLazaro\Larallow\Models\Role;
use EduLazaro\Larallow\Tests\Support\Models\Account;

class IsRoleTenantTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_only_roles_belonging_to_the_given_account_tenant()
    {
        $account = Account::create();

        // Roles belonging to this account (tenant)
        Role::create([
            'handle' => 'role-1',
            'tenant_type' => $account->getMorphClass(),
            'tenant_id' => $account->getKey(),
        ]);

        Role::create([
            'handle' => 'role-2',
            'tenant_type' => $account->getMorphClass(),
            'tenant_id' => $account->getKey(),
        ]);

        // Role belonging to another (unrelated) tenant
        Role::create([
            'handle' => 'role-3',
            'tenant_type' => 'Another\Fake\Model',
            'tenant_id' => 999,
        ]);

        $roles = $account->roles()->get();

        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('handle', 'role-1'));
        $this->assertTrue($roles->contains('handle', 'role-2'));
        $this->assertFalse($roles->contains('handle', 'role-3'));
    }

    /** @test */
    public function it_returns_an_empty_collection_when_account_has_no_roles()
    {
        $account = Account::create();

        $roles = $account->roles()->get();

        $this->assertTrue($roles->isEmpty());
    }
}
