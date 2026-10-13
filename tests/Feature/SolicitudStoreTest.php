<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicitante_can_submit_and_view_the_created_solicitud(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312456789',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $fecha = now()->toDateString();
        $response = $this->actingAs($user)->post(route('solicitudes.store'), [
            'tipo' => SolicitudTipo::Permiso->value,
            'fecha_permiso' => $fecha,
            'hora_inicio_permiso' => '08:00',
            'hora_fin_permiso' => '10:00',
            'motivo_permiso' => 'reunion',
        ]);

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame($user->id, $solicitud->creado_por);

        $response->assertRedirect(route('solicitudes.show', $solicitud));

        $this->actingAs($user)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Detalle del trámite');
    }

    public function test_another_solicitante_cannot_view_someone_elses_solicitud(): void
    {
        $owner = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312456789',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);
        $other = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '0998765432',
            'carrera' => 'software',
            'celular' => '0987654321',
        ]);

        $fecha = now()->toDateString();
        $this->actingAs($owner)->post(route('solicitudes.store'), [
            'tipo' => SolicitudTipo::Permiso->value,
            'fecha_permiso' => $fecha,
            'hora_inicio_permiso' => '08:00',
            'hora_fin_permiso' => '10:00',
            'motivo_permiso' => 'reunion',
        ]);

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);

        $this->actingAs($other)
            ->get(route('solicitudes.show', $solicitud))
            ->assertForbidden();
    }
}
