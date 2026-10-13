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
            ->assertSee('Secretaria Académica')
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
            ->assertSee($secretaria->email)
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
            ->assertSee('Decano Facultad')
            ->assertSee($decano->email);
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
