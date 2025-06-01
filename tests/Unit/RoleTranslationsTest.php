<?php

namespace EduLazaro\Larallow\Tests\Unit;

use EduLazaro\Larallow\Tests\TestCase;
use EduLazaro\Larallow\Models\Role;
use Illuminate\Support\Facades\App;

class RoleTranslationsTest extends TestCase
{
    /** @test */
    public function it_returns_translation_for_current_locale()
    {
        $role = new Role();
        $role->translations = [
            'name' => [
                'es' => 'Administrador',
                'en' => 'Administrator',
            ],
        ];

        App::setLocale('es');

        $this->assertEquals('Administrador', $role->name);
        $this->assertEquals('Administrador', $role->getTranslation('name'));
    }

    /** @test */
    public function it_falls_back_to_default_locale_translation_if_current_locale_not_found()
    {
        $role = new Role();
        $role->translations = [
            'name' => [
                'en' => 'Administrator',
            ],
        ];

        App::setLocale('fr');
        config(['app.fallback_locale' => 'en']);

        $this->assertEquals('Administrator', $role->name);
        $this->assertEquals('Administrator', $role->getTranslation('name'));
    }

    /** @test */
    public function it_returns_database_value_if_no_translation_found()
    {
        $role = new Role();
        $role->name = 'Admin';

        $role->translations = [];

        App::setLocale('fr');
        config(['app.fallback_locale' => 'en']);

        $this->assertEquals('Admin', $role->name);
        $this->assertEquals('Admin', $role->getTranslation('name'));
    }

    /** @test */
    public function it_returns_default_value_if_no_translation_or_attribute()
    {
        $role = new Role();
        $role->translations = [];

        App::setLocale('fr');
        config(['app.fallback_locale' => 'en']);

        $this->assertNull($role->name);
        $this->assertEquals('default', $role->getTranslation('name', null, 'default'));
    }

    /** @test */
    public function it_can_set_and_get_translation_for_a_given_locale()
    {
        $role = new Role();
        $role->translations = [];

        $role->setTranslation('name', 'fr', 'Administrateur');
        $role->setTranslation('name', 'es', 'Administrador');

        $this->assertEquals('Administrateur', $role->getTranslation('name', 'fr'));
        $this->assertEquals('Administrador', $role->getTranslation('name', 'es'));
    }
}
