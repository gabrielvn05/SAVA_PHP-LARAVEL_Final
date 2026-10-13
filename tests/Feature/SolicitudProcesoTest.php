<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudProcesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_lists_all_requests_and_only_links_to_details(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000101');
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000102');
        $enRevision = $this->crearSolicitud($solicitante, SolicitudEstado::EnRevisionSecretaria, 'Motivo en revisión');
        $aprobada = $this->crearSolicitud($solicitante, SolicitudEstado::Aprobada, 'Motivo ya aprobado');

        $this->actingAs($secretaria)
            ->get(route('solicitudes.proceso'))
            ->assertOk()
            ->assertSee('Proceso de aprobación')
            ->assertSee('Ver detalles')
            ->assertSee('Motivo en revisión')
            ->assertSee('Motivo ya aprobado')
            ->assertDontSee('name="aprobado"', false)
            ->assertSee(route('solicitudes.show', $enRevision), false)
            ->assertSee(route('solicitudes.show', $aprobada), false);
    }

    public function test_secretaria_approves_from_the_detail_page_with_default_observation(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000103');
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000104');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::EnRevisionSecretaria);

        $this->actingAs($secretaria)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Acciones')
            ->assertSee('Aprobar')
            ->assertSee('Rechazar')
            ->assertSee('Motivo del rechazo');

        $this->actingAs($secretaria)
            ->from(route('solicitudes.show', $solicitud))
            ->post(route('solicitudes.revisar', $solicitud), [
                'aprobado' => '1',
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $solicitud->refresh();
        $this->assertSame(SolicitudEstado::PendienteAprobacionDecano, $solicitud->estado);
        $this->assertSame('Aprobado en revisión de Secretaría.', $solicitud->observaciones_secretaria);
        $this->assertNull($solicitud->firmado_por);
        $this->assertNull($solicitud->fecha_firma);
    }

    public function test_secretaria_rejection_from_detail_requires_a_comment(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000105');
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000106');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::EnRevisionSecretaria);

        $this->actingAs($secretaria)
            ->from(route('solicitudes.show', $solicitud))
            ->post(route('solicitudes.revisar', $solicitud), [
                'aprobado' => '0',
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud))
            ->assertSessionHas('error');

        $this->assertSame(SolicitudEstado::EnRevisionSecretaria, $solicitud->fresh()->estado);
    }

    public function test_secretaria_cannot_sign_when_the_request_is_waiting_for_the_dean(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000110');
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000111');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::PendienteAprobacionDecano);

        $this->actingAs($secretaria)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertDontSee('data-proceso-acciones', false)
            ->assertDontSee('Aprobar')
            ->assertDontSee('Rechazar');

        $this->actingAs($secretaria)
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '1',
            ])
            ->assertForbidden();
    }

    public function test_decano_sees_sign_actions_only_on_pending_dean_requests(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000112');
        $decano = $this->usuario(AppRole::Decano, '1312000113');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::PendienteAprobacionDecano);

        $this->actingAs($decano)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Acciones')
            ->assertSee('Aprobar')
            ->assertSee('Rechazar')
            ->assertSee('firma de Decano')
            ->assertSee(route('solicitudes.aprobar', $solicitud), false)
            ->assertDontSee(route('solicitudes.revisar', $solicitud), false);
    }

    public function test_decano_approves_from_detail_with_default_observation(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000107');
        $decano = $this->usuario(AppRole::Decano, '1312000108');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::PendienteAprobacionDecano);

        $this->actingAs($decano)
            ->from(route('solicitudes.show', $solicitud))
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '1',
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $solicitud->refresh();
        $this->assertSame(SolicitudEstado::Aprobada, $solicitud->estado);
        $this->assertSame('Aprobado y firmado por Decano.', $solicitud->observaciones_decano);
        $this->assertSame($decano->id, $solicitud->firmado_por);
        $this->assertNotNull($solicitud->fecha_firma);
    }

    public function test_decano_rejection_from_detail_requires_a_comment(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000114');
        $decano = $this->usuario(AppRole::Decano, '1312000115');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::PendienteAprobacionDecano);

        $this->actingAs($decano)
            ->from(route('solicitudes.show', $solicitud))
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '0',
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud))
            ->assertSessionHas('error');

        $this->assertSame(SolicitudEstado::PendienteAprobacionDecano, $solicitud->fresh()->estado);
    }

    public function test_decano_rejection_from_detail_records_the_reason_and_signature(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000116');
        $decano = $this->usuario(AppRole::Decano, '1312000117');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::PendienteAprobacionDecano);

        $this->actingAs($decano)
            ->from(route('solicitudes.show', $solicitud))
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '0',
                'observaciones_decano' => 'Fuera de plazo institucional.',
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $solicitud->refresh();
        $this->assertSame(SolicitudEstado::Rechazada, $solicitud->estado);
        $this->assertSame('Fuera de plazo institucional.', $solicitud->observaciones_decano);
        $this->assertSame($decano->id, $solicitud->firmado_por);
        $this->assertNotNull($solicitud->fecha_firma);

        $this->actingAs($solicitante)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee($decano->nombreCompleto(), false)
            ->assertSee('Fuera de plazo institucional.', false);
    }

    public function test_secretaria_then_decano_complete_the_full_approval_pipeline(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000118');
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000119');
        $decano = $this->usuario(AppRole::Decano, '1312000120');
        $solicitud = $this->crearSolicitud($solicitante, SolicitudEstado::EnRevisionSecretaria);

        $this->actingAs($secretaria)
            ->post(route('solicitudes.revisar', $solicitud), ['aprobado' => '1']);

        $this->assertSame(SolicitudEstado::PendienteAprobacionDecano, $solicitud->fresh()->estado);

        $this->actingAs($decano)
            ->get(route('solicitudes.proceso'))
            ->assertOk()
            ->assertSee($solicitud->motivo);

        $this->actingAs($decano)
            ->post(route('solicitudes.aprobar', $solicitud), ['aprobado' => '1']);

        $solicitud->refresh();
        $this->assertSame(SolicitudEstado::Aprobada, $solicitud->estado);
        $this->assertSame($secretaria->id, $solicitud->revisado_por);
        $this->assertSame($decano->id, $solicitud->firmado_por);
        $this->assertSame('Aprobado en revisión de Secretaría.', $solicitud->observaciones_secretaria);
        $this->assertSame('Aprobado y firmado por Decano.', $solicitud->observaciones_decano);
    }

    public function test_solicitante_cannot_open_the_approval_inbox(): void
    {
        $solicitante = $this->usuario(AppRole::Administrativo, '1312000109');

        $this->actingAs($solicitante)
            ->get(route('solicitudes.proceso'))
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

    private function crearSolicitud(User $creador, SolicitudEstado $estado, string $motivo = 'Viaje académico de prueba'): Solicitud
    {
        return Solicitud::query()->create([
            'creado_por' => $creador->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDay()->toDateString(),
            'motivo' => $motivo,
            'estado' => $estado,
            'detalle' => ['codigo_tramite' => 'SAVA-TEST-PROC'],
        ]);
    }
}
