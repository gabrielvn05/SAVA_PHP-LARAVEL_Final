<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermisoSolicitudTest extends TestCase
{
    use RefreshDatabase;

    public function test_permiso_usa_un_solo_dia_y_campos_de_horas(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1315591303',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $fecha = now()->toDateString();

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_permiso' => $fecha,
                'hora_inicio_permiso' => '09:00',
                'hora_fin_permiso' => '11:30',
                'motivo_permiso' => 'reunion',
                'observaciones' => 'Reunión de coordinación académica.',
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame($fecha, $solicitud->fecha_inicio->toDateString());
        $this->assertSame($fecha, $solicitud->fecha_fin->toDateString());
        $this->assertSame('Reunión', $solicitud->motivo);
        $this->assertSame('09:00', $solicitud->detalle['hora_inicio_permiso'] ?? null);
        $this->assertSame('11:30', $solicitud->detalle['hora_fin_permiso'] ?? null);
    }

    public function test_permiso_rechaza_hora_fin_anterior(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000101',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $this->actingAs($user)
            ->from(route('solicitudes.wizard'))
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_permiso' => now()->toDateString(),
                'hora_inicio_permiso' => '14:00',
                'hora_fin_permiso' => '13:00',
                'motivo_permiso' => 'tramites_personales',
            ])
            ->assertRedirect(route('solicitudes.wizard'))
            ->assertSessionHas('error');

        $this->assertSame(0, Solicitud::query()->count());
    }

    public function test_oficio_html_permiso_muestra_horas(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1315591303',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Reunión',
            'estado' => \App\Enums\SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-PER-MJ',
                'fecha_permiso' => now()->toDateString(),
                'hora_inicio_permiso' => '10:00',
                'hora_fin_permiso' => '12:00',
                'motivo_permiso' => 'reunion',
            ],
        ]);

        $html = app(\App\Services\OficioPreviewService::class)->renderHtmlRespaldo($solicitud);

        $this->assertStringContainsString('10:00', $html);
        $this->assertStringContainsString('12:00', $html);
        $this->assertStringContainsString('Reunión', $html);
    }
}
