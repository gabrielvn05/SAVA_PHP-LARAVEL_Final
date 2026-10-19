<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\User;
use App\Models\UserCapability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioRolTest extends TestCase
{
    use RefreshDatabase;

    public function test_decano_can_open_users_and_change_a_role(): void
    {
        $decano = $this->usuario(AppRole::Decano, '1313000001');
        $docente = $this->usuario(AppRole::Docente, '1313000002');

        $this->actingAs($decano)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('Cambiar rol')
            ->assertSee('Delegar capacidad')
            ->assertSee('Firmar o dar visto bueno')
            ->assertSee('Emitir notificaciones de faltas (TH)')
            ->assertSee($docente->email)
            ->assertDontSee('Superusuario', false);

        $this->actingAs($decano)
            ->from(route('admin.usuarios.index'))
            ->patch(route('admin.usuarios.rol', $docente), [
                'rol' => AppRole::Secretaria->value,
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHas('success');

        $this->assertSame(AppRole::Secretaria, $docente->fresh()->rol);
    }

    public function test_superusuario_can_change_a_role(): void
    {
        $super = $this->usuario(AppRole::Superusuario, '1313000003');
        $admin = $this->usuario(AppRole::Administrativo, '1313000004');

        $this->actingAs($super)
            ->patch(route('admin.usuarios.rol', $admin), [
                'rol' => AppRole::Decano->value,
            ])
            ->assertRedirect();

        $this->assertSame(AppRole::Decano, $admin->fresh()->rol);
    }

    public function test_decano_cannot_assign_superusuario(): void
    {
        $decano = $this->usuario(AppRole::Decano, '1313000005');
        $docente = $this->usuario(AppRole::Docente, '1313000006');

        $this->actingAs($decano)
            ->patch(route('admin.usuarios.rol', $docente), [
                'rol' => AppRole::Superusuario->value,
            ])
            ->assertSessionHasErrors('rol');

        $this->assertSame(AppRole::Docente, $docente->fresh()->rol);
    }

    public function test_cambiar_solo_estado_no_modifica_el_rol(): void
    {
        $super = $this->usuario(AppRole::Superusuario, '1313000007');
        $docente = $this->usuario(AppRole::Docente, '1313000008');

        $this->actingAs($super)
            ->patch(route('admin.usuarios.estado', $docente), [
                'activo' => '0',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $docente->fresh();
        $this->assertSame(AppRole::Docente, $fresh->rol);
        $this->assertFalse($fresh->activo);
    }

    public function test_superusuario_no_aparece_en_listado_para_decano(): void
    {
        $super = $this->usuario(AppRole::Superusuario, '1313000099');
        $decano = $this->usuario(AppRole::Decano, '1313000009');

        $this->actingAs($decano)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertDontSee($super->email, false);
    }

    public function test_superusuario_rechaza_capacidad_invalida(): void
    {
        $super = $this->usuario(AppRole::Superusuario, '1313000011');
        $docente = $this->usuario(AppRole::Docente, '1313000012');

        $this->actingAs($super)
            ->post(route('admin.usuarios.delegate', $docente), [
                'capabilities' => ['hackear_sistema'],
            ])
            ->assertSessionHasErrors('capabilities.0');
    }

    public function test_decano_delega_firma_y_faltas_a_secretaria_y_puede_quitarlas(): void
    {
        $decano = $this->usuario(AppRole::Decano, '1313000021');
        $secretaria = $this->usuario(AppRole::Secretaria, '1313000022');

        $this->actingAs($decano)
            ->post(route('admin.usuarios.delegate', $secretaria), [
                'capabilities' => [
                    CapabilityType::AprobarSolicitudes->value,
                    CapabilityType::EmitirNotificacionesFaltas->value,
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue($secretaria->fresh()->hasCapability(CapabilityType::AprobarSolicitudes));
        $this->assertTrue($secretaria->fresh()->hasCapability(CapabilityType::EmitirNotificacionesFaltas));
        $this->assertSame(
            $decano->id,
            UserCapability::query()
                ->where('user_id', $secretaria->id)
                ->where('capability', CapabilityType::AprobarSolicitudes->value)
                ->value('otorgado_por'),
        );

        $this->actingAs($decano)
            ->post(route('admin.usuarios.delegate', $secretaria), [
                'capabilities' => [],
            ])
            ->assertRedirect();

        $this->assertFalse($secretaria->fresh()->hasCapability(CapabilityType::AprobarSolicitudes));
        $this->assertFalse($secretaria->fresh()->hasCapability(CapabilityType::EmitirNotificacionesFaltas));
        $this->assertTrue($secretaria->fresh()->hasCapability(CapabilityType::RevisarSolicitudes));
    }

    public function test_decano_no_puede_delegar_gestion_de_usuarios(): void
    {
        $decano = $this->usuario(AppRole::Decano, '1313000023');
        $secretaria = $this->usuario(AppRole::Secretaria, '1313000024');

        $this->actingAs($decano)
            ->post(route('admin.usuarios.delegate', $secretaria), [
                'capabilities' => [CapabilityType::GestionarUsuarios->value],
            ])
            ->assertSessionHasErrors('capabilities.0');

        $this->assertFalse($secretaria->fresh()->hasCapability(CapabilityType::GestionarUsuarios));
    }

    public function test_superusuario_crea_usuario_interno_con_cedula(): void
    {
        $super = $this->usuario(AppRole::Superusuario, '1313000098');

        $this->actingAs($super)
            ->get(route('admin.usuarios.index'))
            ->assertOk()
            ->assertSee('Cédula de identidad', false)
            ->assertSee('admin-cedula-input', false);

        $this->actingAs($super)
            ->post(route('admin.usuarios.store'), [
                'email' => 'nuevo.interno@uleam.edu.ec',
                'password' => 'ClaveSegura1!',
                'nombres' => 'Ana',
                'apellidos' => 'Perez',
                'cedula' => '1712345675',
                'rol' => AppRole::Docente->value,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $creado = User::query()->where('email', 'nuevo.interno@uleam.edu.ec')->first();
        $this->assertNotNull($creado);
        $this->assertSame('1712345675', $creado->cedula);
    }

    public function test_superusuario_rechaza_cedula_invalida_o_repetida(): void
    {
        $super = $this->usuario(AppRole::Superusuario, '1313000097');

        $this->actingAs($super)
            ->from(route('admin.usuarios.index'))
            ->post(route('admin.usuarios.store'), [
                'email' => 'sin.cedula@uleam.edu.ec',
                'password' => 'ClaveSegura1!',
                'nombres' => 'Ana',
                'apellidos' => 'Perez',
                'cedula' => '17123ABC75',
                'rol' => AppRole::Docente->value,
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHasErrors('cedula');

        $this->actingAs($super)
            ->from(route('admin.usuarios.index'))
            ->post(route('admin.usuarios.store'), [
                'email' => 'cedula.corta@uleam.edu.ec',
                'password' => 'ClaveSegura1!',
                'nombres' => 'Ana',
                'apellidos' => 'Perez',
                'cedula' => '17123',
                'rol' => AppRole::Docente->value,
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHasErrors('cedula');

        $this->actingAs($super)
            ->from(route('admin.usuarios.index'))
            ->post(route('admin.usuarios.store'), [
                'email' => 'cedula.repetida@uleam.edu.ec',
                'password' => 'ClaveSegura1!',
                'nombres' => 'Ana',
                'apellidos' => 'Perez',
                'cedula' => '1313000097',
                'rol' => AppRole::Docente->value,
            ])
            ->assertRedirect(route('admin.usuarios.index'))
            ->assertSessionHasErrors('cedula');

        $this->assertNull(User::query()->where('email', 'cedula.repetida@uleam.edu.ec')->first());
    }

    public function test_secretaria_cannot_manage_users(): void
    {
        $secretaria = $this->usuario(AppRole::Secretaria, '1313000010');

        $this->actingAs($secretaria)
            ->get(route('admin.usuarios.index'))
            ->assertForbidden();
    }

    private function usuario(AppRole $rol, string $cedula): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'cedula' => $cedula,
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);
    }
}
