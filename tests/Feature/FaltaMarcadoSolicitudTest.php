<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaltaMarcadoSolicitudTest extends TestCase
{
    use RefreshDatabase;

    public function test_face_id_entrada_salida_guarda_ambas_horas(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1315591303',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $fecha = now()->subDays(2)->toDateString();

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::FaltaMarcado->value,
                'jornada' => 'primera_jornada',
                'fecha_incidente' => $fecha,
                'tipo_marcacion_omitida' => 'entrada_salida',
                'hora_real_ingreso' => '08:00',
                'hora_real_salida' => '17:00',
                'motivo_falta_registro' => 'falla_face_id',
                'observaciones' => 'El lector no respondió en ambos turnos.',
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame('Falla técnica del sistema Face ID', $solicitud->motivo);
        $this->assertSame('entrada_salida', $solicitud->detalle['tipo_marcacion_omitida'] ?? null);
        $this->assertSame('08:00', $solicitud->detalle['hora_real_ingreso'] ?? null);
        $this->assertSame('17:00', $solicitud->detalle['hora_real_salida'] ?? null);
    }

    public function test_face_id_solo_entrada_no_exige_hora_salida(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000101',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::FaltaMarcado->value,
                'jornada' => 'segunda_jornada',
                'fecha_incidente' => now()->toDateString(),
                'tipo_marcacion_omitida' => 'entrada',
                'hora_real_ingreso' => '14:05',
                'motivo_falta_registro' => 'olvido_docente',
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertSame('Olvido del docente', $solicitud->motivo);
        $this->assertArrayNotHasKey('hora_real_salida', $solicitud->detalle ?? []);
    }

    public function test_oficio_html_face_id_muestra_entrada_y_salida(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1315591303',
            'carrera' => 'software',
        ]);

        $fecha = now()->toDateString();

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::FaltaMarcado,
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'motivo' => 'Falla técnica del sistema Face ID',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-REC-MJ',
                'fecha_incidente' => $fecha,
                'tipo_marcacion_omitida' => 'entrada_salida',
                'hora_real_ingreso' => '07:55',
                'hora_real_salida' => '16:10',
                'motivo_falta_registro' => 'falla_face_id',
                'observaciones' => 'Dos intentos fallidos.',
            ],
        ]);

        $html = app(\App\Services\OficioPreviewService::class)->renderHtmlRespaldo($solicitud);

        $this->assertStringContainsString('Entrada y salida', $html);
        $this->assertStringContainsString('07:55', $html);
        $this->assertStringContainsString('16:10', $html);
        $this->assertStringContainsString('Dos intentos fallidos.', $html);
        $this->assertStringNotContainsString('Descripción complementaria', $html);
    }
}
