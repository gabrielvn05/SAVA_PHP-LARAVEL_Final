<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JustificacionAtrasoSolicitudTest extends TestCase
{
    use RefreshDatabase;

    public function test_justificacion_atraso_usa_fecha_hoy_y_horas(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1315591303',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $hoy = now()->toDateString();

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Justificacion->value,
                'fecha_atraso' => $hoy,
                'hora_llegada_establecida' => '08:00',
                'hora_llegada_real' => '08:45',
                'motivo_atraso' => 'problemas_viales',
                'observaciones' => 'Cierre parcial en la vía a Manta.',
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame($hoy, $solicitud->fecha_inicio->toDateString());
        $this->assertSame($hoy, $solicitud->fecha_fin->toDateString());
        $this->assertSame('Problemas viales', $solicitud->motivo);
        $this->assertSame('08:00', $solicitud->detalle['hora_llegada_establecida'] ?? null);
        $this->assertSame('08:45', $solicitud->detalle['hora_llegada_real'] ?? null);
    }

    public function test_justificacion_rechaza_fecha_distinta_a_hoy(): void
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
                'tipo' => SolicitudTipo::Justificacion->value,
                'fecha_atraso' => now()->subDay()->toDateString(),
                'hora_llegada_establecida' => '08:00',
                'hora_llegada_real' => '09:00',
                'motivo_atraso' => 'averia_transporte',
            ])
            ->assertRedirect(route('solicitudes.wizard'))
            ->assertSessionHas('error');

        $this->assertSame(0, Solicitud::query()->count());
    }

    public function test_oficio_html_justificacion_muestra_horas_y_motivo(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1315591303',
            'carrera' => 'software',
        ]);

        $hoy = now()->toDateString();

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Justificacion,
            'fecha_inicio' => $hoy,
            'fecha_fin' => $hoy,
            'motivo' => 'Avería de transporte',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-JUS-MJ',
                'fecha_atraso' => $hoy,
                'hora_llegada_establecida' => '07:30',
                'hora_llegada_real' => '08:15',
                'motivo_atraso' => 'averia_transporte',
            ],
        ]);

        $html = app(\App\Services\OficioPreviewService::class)->renderHtmlRespaldo($solicitud);

        $this->assertStringContainsString('07:30', $html);
        $this->assertStringContainsString('08:15', $html);
        $this->assertStringContainsString('Avería de transporte', $html);
        $this->assertStringContainsString('Justificación por atraso', $html);
    }
}
