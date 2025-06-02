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
    public function it_returns_roles_related_to_account_tenant()
    {
        $account = Account::create();

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

        $roles = $account->roles()->get();

        $this->assertCount(2, $roles);
        $this->assertTrue($roles->contains('handle', 'role-1'));
        $this->assertTrue($roles->contains('handle', 'role-2'));
    }

    /** @test */
    public function it_returns_empty_collection_if_account_has_no_roles()
    {
        $account = Account::create();

        $roles = $account->roles()->get();

        $this->assertTrue($roles->isEmpty());
    }
}
