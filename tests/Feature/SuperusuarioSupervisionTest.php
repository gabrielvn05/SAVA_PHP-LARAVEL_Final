<?php

namespace Tests\Feature;

use App\Enums\AccesoResultado;
use App\Enums\AccountRequestStatus;
use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\AccesoEvento;
use App\Models\AccountRequest;
use App\Models\User;
use App\Models\UserCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperusuarioSupervisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_ingreso_fallido_queda_visible_para_el_superusuario(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $docente = User::factory()->create([
            'rol' => AppRole::Docente,
            'email' => 'luis.perez@uleam.edu.ec',
            'nombres' => 'Luis',
            'apellidos' => 'Perez',
            'password' => 'SavaTest2026!',
            'cedula' => '1313000101',
            'carrera' => 'software',
            'celular' => '0991234567',
            'force_password_change' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $docente->email,
            'password' => 'clave-mala',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('acceso_eventos', [
            'user_id' => $docente->id,
            'resultado' => AccesoResultado::ClaveIncorrecta->value,
        ]);

        $this->actingAs($super)
            ->get(route('admin.accesos.index'))
            ->assertOk()
            ->assertSee('luis.perez@uleam.edu.ec')
            ->assertSee('Clave incorrecta')
            ->assertSee('Buscar por nombre, correo o cédula', false);
    }

    public function test_el_superusuario_cierra_la_sesion_de_otra_cuenta(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $docente = $this->usuario(AppRole::Docente, 'Luis', 'Perez');
        $docente->forceFill(['session_token' => 'token-activo'])->save();

        $this->actingAs($super)
            ->post(route('admin.accesos.cerrar'), ['usuario_id' => $docente->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotSame('token-activo', $docente->fresh()->session_token);
        $this->assertDatabaseHas('acceso_eventos', [
            'user_id' => $docente->id,
            'actor_id' => $super->id,
            'resultado' => AccesoResultado::SesionCerrada->value,
        ]);

        $this->withSession(['auth_session_token' => 'token-activo'])
            ->actingAs($docente)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_no_cierra_su_propia_sesion_desde_el_apartado(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');

        $this->actingAs($super)
            ->post(route('admin.accesos.cerrar'), ['usuario_id' => $super->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, AccesoEvento::query()->count());
    }

    public function test_muestra_cuentas_inactivas_claves_temporales_y_altas(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $this->usuario(AppRole::Docente, 'Luis', 'Perez', ['activo' => false]);
        $this->usuario(AppRole::Secretaria, 'Marta', 'Velez', ['force_password_change' => true]);
        AccountRequest::query()->create([
            'nombres' => 'Pedro',
            'apellidos' => 'Solis',
            'email' => 'pedro.solis@uleam.edu.ec',
            'cedula' => '1313000199',
            'carrera' => 'software',
            'status' => AccountRequestStatus::Pendiente,
        ]);

        $this->actingAs($super)
            ->get(route('admin.cuentas.index'))
            ->assertOk()
            ->assertSee('Luis Perez')
            ->assertSee('Marta Velez')
            ->assertSee('pedro.solis@uleam.edu.ec');
    }

    public function test_lista_quien_firma_con_una_funcion_del_decano(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $decano = $this->usuario(AppRole::Decano, 'Carlos', 'Mora');
        $secretaria = $this->usuario(AppRole::Secretaria, 'Marta', 'Velez');

        UserCapability::query()->create([
            'user_id' => $secretaria->id,
            'capability' => CapabilityType::AprobarSolicitudes,
            'otorgado_por' => $decano->id,
            'created_at' => now(),
        ]);

        $this->actingAs($super)
            ->get(route('admin.delegaciones.index'))
            ->assertOk()
            ->assertSee('Marta Velez')
            ->assertSee('Firmar o dar visto bueno')
            ->assertSee('Carlos Mora');
    }

    public function test_muestra_una_funcion_extra_aunque_no_tenga_quien_la_otorgo(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $docente = $this->usuario(AppRole::Docente, 'Luis', 'Perez');
        $decano = $this->usuario(AppRole::Decano, 'Carlos', 'Mora');

        UserCapability::query()->create([
            'user_id' => $docente->id,
            'capability' => CapabilityType::AprobarSolicitudes,
            'otorgado_por' => null,
            'created_at' => now(),
        ]);
        UserCapability::query()->create([
            'user_id' => $decano->id,
            'capability' => CapabilityType::EmitirNotificacionesFaltas,
            'otorgado_por' => $super->id,
            'created_at' => now(),
        ]);

        $this->actingAs($super)
            ->get(route('admin.delegaciones.index'))
            ->assertOk()
            ->assertSee('Luis Perez')
            ->assertSee('Carlos Mora')
            ->assertSee('Emitir notificaciones de faltas (TH)');
    }

    public function test_el_panel_y_el_menu_incluyen_los_apartados_nuevos(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');

        $this->actingAs($super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Cuentas por atender')
            ->assertSee('Delegaciones')
            ->assertSee('Accesos y sesiones')
            ->assertSee('Notificaciones TH')
            ->assertSee('Proceso de aprobación')
            ->assertSee('Reportes')
            ->assertSee(route('admin.accesos.index'), false)
            ->assertSee(route('notificaciones-faltas.index'), false)
            ->assertSee(route('reportes.index'), false);
    }

    public function test_otros_roles_no_ven_la_supervision(): void
    {
        $decano = $this->usuario(AppRole::Decano, 'Carlos', 'Mora');

        $this->actingAs($decano)->get(route('admin.accesos.index'))->assertForbidden();
        $this->actingAs($decano)->get(route('admin.cuentas.index'))->assertForbidden();
        $this->actingAs($decano)->get(route('admin.delegaciones.index'))->assertForbidden();
        $this->actingAs($decano)->post(route('admin.accesos.cerrar'), [
            'usuario_id' => $decano->id,
        ])->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function usuario(AppRole $rol, string $nombres, string $apellidos, array $extra = []): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'carrera' => 'software',
            'celular' => '0991234567',
            ...$extra,
        ]);
    }
}
