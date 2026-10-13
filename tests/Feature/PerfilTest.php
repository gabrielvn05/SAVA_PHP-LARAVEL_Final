<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_profile_is_redirected_to_completion_window(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '',
            'carrera' => '',
            'celular' => '',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('perfil.completar'));

        $this->actingAs($user)
            ->get(route('perfil.completar'))
            ->assertOk()
            ->assertSee('Actualiza tus datos');
    }

    public function test_completion_window_saves_institutional_data(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '',
            'carrera' => '',
            'celular' => '',
        ]);

        $this->actingAs($user)
            ->put(route('perfil.update'), [
                'cedula' => '1312456789',
                'carrera' => 'software',
                'celular' => '0991234567',
            ])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame('1312456789', $user->cedula);
        $this->assertSame('software', $user->carrera);
        $this->assertSame('0991234567', $user->celular);
        $this->assertFalse($user->needsProfileCompletion());
    }

    public function test_profile_settings_can_be_updated_later(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312456789',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $this->actingAs($user)
            ->get(route('perfil.edit'))
            ->assertOk()
            ->assertSee('Perfil y configuración')
            ->assertSee('Contacto y datos institucionales')
            ->assertSee('Estos datos de identidad no se pueden modificar');

        $this->actingAs($user)
            ->put(route('perfil.update'), [
                'cedula' => '1312456789',
                'carrera' => 'biologia',
                'celular' => '0987654321',
            ])
            ->assertRedirect(route('perfil.edit'));

        $user->refresh();
        $this->assertSame('1312456789', $user->cedula);
        $this->assertSame('biologia', $user->carrera);
        $this->assertSame('0987654321', $user->celular);
    }

    public function test_superuser_is_not_forced_to_complete_profile(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Superusuario,
            'cedula' => '',
            'carrera' => '',
            'celular' => '',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }
}
