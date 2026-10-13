<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\User;
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
