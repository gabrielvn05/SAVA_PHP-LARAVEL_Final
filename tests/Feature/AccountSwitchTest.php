<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_microsoft_profile_can_open_login_to_switch_account(): void
    {
        $microsoft = $this->microsoftUser();

        $this->actingAs($microsoft)
            ->get(route('login'))
            ->assertOk()
            ->assertSee($microsoft->email)
            ->assertSee('Secretaría');
    }

    public function test_password_login_replaces_microsoft_session_even_if_profile_is_incomplete(): void
    {
        $microsoft = $this->microsoftUser();
        $secretaria = $this->secretaria();

        $this->actingAs($microsoft)
            ->post(route('login.store'), [
                'email' => $secretaria->email,
                'password' => 'SavaTest2026!',
            ])
            ->assertRedirect(route('solicitudes.proceso'));

        $this->assertAuthenticatedAs($secretaria);

        $this->get(route('solicitudes.proceso'))
            ->assertOk()
            ->assertSee('Proceso de aprobación')
            ->assertSee('SECRETARIA ACADÉMICA')
            ->assertDontSee($microsoft->email);
    }

    public function test_secretaria_lands_on_approval_inbox_not_the_previous_microsoft_dashboard(): void
    {
        $secretaria = $this->secretaria();

        $this->post(route('login.store'), [
            'email' => $secretaria->email,
            'password' => 'SavaTest2026!',
        ])->assertRedirect(route('solicitudes.proceso'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('SECRETARIA ACADÉMICA')
            ->assertSee('Secretaría');
    }

    public function test_decano_password_login_opens_the_approval_inbox(): void
    {
        $decano = User::factory()->create([
            'email' => 'decano@uleam.edu.ec',
            'nombres' => 'Decano',
            'apellidos' => 'Facultad',
            'rol' => AppRole::Decano,
            'password' => 'SavaTest2026!',
            'cedula' => '1234567890',
            'carrera' => 'software',
            'celular' => '0990000000',
            'force_password_change' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $decano->email,
            'password' => 'SavaTest2026!',
        ])
            ->assertRedirect(route('solicitudes.proceso'));

        $this->get(route('solicitudes.proceso'))
            ->assertOk()
            ->assertSee('DECANO FACULTAD')
            ->assertSee($decano->cedula);
    }

    public function test_a_newer_login_invalidates_the_previous_session(): void
    {
        $user = User::factory()->create([
            'email' => 'docente.sesion@uleam.edu.ec',
            'password' => 'SavaTest2026!',
            'rol' => AppRole::Docente,
            'cedula' => '0951234566',
            'carrera' => 'software',
            'celular' => '0990000000',
            'force_password_change' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'SavaTest2026!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session('auth_session_token'));

        $user->forceFill(['session_token' => 'token-de-otro-dispositivo'])->save();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function microsoftUser(): User
    {
        return User::factory()->create([
            'email' => 'e1316861275@live.uleam.edu.ec',
            'nombres' => 'Gabriel',
            'apellidos' => 'Velez',
            'rol' => AppRole::Administrativo,
            'microsoft_id' => 'oid-gabriel',
            'cedula' => '',
            'carrera' => '',
            'celular' => '',
        ]);
    }

    private function secretaria(): User
    {
        return User::factory()->create([
            'email' => 'secretaria@uleam.edu.ec',
            'nombres' => 'Secretaria',
            'apellidos' => 'Académica',
            'rol' => AppRole::Secretaria,
            'password' => 'SavaTest2026!',
            'cedula' => '0987654321',
            'carrera' => 'software',
            'celular' => '0990000000',
            'force_password_change' => false,
        ]);
    }
}
