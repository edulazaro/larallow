<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Models\Role;
use EduLazaro\Larallow\Tests\Support\Models\Client;
use EduLazaro\Larallow\Tests\Support\Models\User;
use EduLazaro\Larallow\Tests\TestCase;
use InvalidArgumentException;

/**
 * El actor_type de un rol, por el camino de Roles::assign().
 *
 * La condicion que lo comprobaba estaba escrita `!$actorClass !== 'actorType'`, que es
 * siempre true, asi que ningun rol con actor_type se podia asignar por aqui, ni siquiera
 * al actor que el rol exige. Sobrevivio porque la suite solo ejercitaba assignRole(),
 * que no pasa por esta validacion.
 */
class AssignRespectsActorTypeTest extends TestCase
{
    public function test_a_role_restricted_to_an_actor_type_can_be_assigned_to_that_actor(): void
    {
        $role = Role::create(['handle' => 'editor', 'actor_type' => User::class]);
        $user = User::create();

        $user->roles([$role->id])->assign();

        $this->assertTrue($user->fresh()->roles->contains($role));
    }

    public function test_the_same_role_is_refused_for_another_actor_type(): void
    {
        $role = Role::create(['handle' => 'editor', 'actor_type' => User::class]);
        $client = Client::create();

        $this->expectException(InvalidArgumentException::class);

        $client->roles([$role->id])->assign();
    }

    public function test_a_role_with_no_actor_type_takes_anyone(): void
    {
        $role = Role::create(['handle' => 'editor']);
        $client = Client::create();

        $client->roles([$role->id])->assign();

        $this->assertTrue($client->fresh()->roles->contains($role));
    }

    public function test_the_error_names_the_role_by_its_handle(): void
    {
        // El mensaje usaba $role->name, que es nullable, asi que un rol sin nombre
        // producia "of role ''" y no decia cual era. handle es obligatorio.
        $role = Role::create(['handle' => 'editor', 'actor_type' => User::class]);
        $client = Client::create();

        try {
            $client->roles([$role->id])->assign();
            $this->fail('deberia haber lanzado');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("role 'editor'", $e->getMessage());
        }
    }
}
