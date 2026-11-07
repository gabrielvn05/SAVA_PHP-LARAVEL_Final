<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_superusuario_ve_logs_de_todos_los_usuarios(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $docente = $this->usuario(AppRole::Docente, 'Luis', 'Perez');
        $secretaria = $this->usuario(AppRole::Secretaria, 'Marta', 'Velez');

        $this->log($docente, 'INSERT', 'solicitudes', ['motivo' => 'Permiso personal']);
        $this->log($secretaria, 'UPDATE', 'users', ['rol' => 'docente'], ['rol' => 'secretaria']);

        $this->actingAs($super)
            ->get(route('admin.auditoria.index'))
            ->assertOk()
            ->assertSee('Auditoría')
            ->assertSee('Luis Perez')
            ->assertSee('Marta Velez')
            ->assertSee('Creación')
            ->assertSee('Actualización')
            ->assertSee('10.1.1.8');
    }

    public function test_el_detalle_oculta_claves_y_muestra_el_cambio(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $docente = $this->usuario(AppRole::Docente, 'Luis', 'Perez');

        $log = $this->log($docente, 'UPDATE', 'users', [
            'email' => 'luis@uleam.edu.ec',
            'password' => 'hash-secreto-no-visible',
        ], [
            'email' => 'anterior@uleam.edu.ec',
            'password' => 'hash-anterior',
        ]);

        $this->actingAs($super)
            ->get(route('admin.auditoria.show', $log))
            ->assertOk()
            ->assertSee('luis@uleam.edu.ec')
            ->assertSee('••••')
            ->assertDontSee('hash-secreto-no-visible')
            ->assertDontSee('hash-anterior');
    }

    public function test_filtra_por_usuario(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');
        $docente = $this->usuario(AppRole::Docente, 'Luis', 'Perez');
        $secretaria = $this->usuario(AppRole::Secretaria, 'Marta', 'Velez');

        $this->log($docente, 'INSERT', 'solicitudes', ['motivo' => 'Viaje docente']);
        $this->log($secretaria, 'DELETE', 'biblioteca_documentos', null, ['titulo' => 'Oficio viejo']);

        $this->actingAs($super)
            ->get(route('admin.auditoria.index', ['usuario' => $docente->id]))
            ->assertOk()
            ->assertSee($docente->email)
            ->assertDontSee($secretaria->email);
    }

    public function test_otros_roles_no_acceden(): void
    {
        foreach ([AppRole::Decano, AppRole::Secretaria, AppRole::Docente] as $rol) {
            $usuario = $this->usuario($rol, 'Otro', 'Usuario');

            $this->actingAs($usuario)
                ->get(route('admin.auditoria.index'))
                ->assertForbidden();
        }
    }

    public function test_el_panel_del_superusuario_enlaza_auditoria(): void
    {
        $super = $this->usuario(AppRole::Superusuario, 'Ana', 'Ruiz');

        $this->actingAs($super)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Auditoría')
            ->assertSee(route('admin.auditoria.index'), false);
    }

    private function usuario(AppRole $rol, string $nombres, string $apellidos): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $new
     * @param  array<string, mixed>|null  $old
     */
    private function log(User $actor, string $action, string $table, ?array $new, ?array $old = null): AuditLog
    {
        return AuditLog::query()->create([
            'table_name' => $table,
            'record_id' => $actor->id,
            'action' => $action,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'old_data' => $old,
            'new_data' => $new,
            'ip_address' => '10.1.1.8',
            'user_agent' => 'PHPUnit',
        ]);
    }
}
