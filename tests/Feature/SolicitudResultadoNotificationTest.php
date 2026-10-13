<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Mail\SolicitudResultadoMail;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SolicitudResultadoNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_page_renders_the_detailed_ficha(): void
    {
        $user = $this->solicitante();
        $solicitud = $this->solicitudPendienteDecano($user);

        $this->actingAs($user)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Vista previa de la solicitud')
            ->assertSee('Oficio institucional')
            ->assertSee('doc-viewer', false)
            ->assertSee(route('solicitudes.preview-oficio', $solicitud), false);

        $preview = $this->actingAs($user)
            ->get(route('solicitudes.preview-oficio', $solicitud))
            ->assertOk();

        $contentType = (string) $preview->headers->get('content-type');
        if (str_contains($contentType, 'pdf')) {
            $preview->assertHeader('content-type', 'application/pdf');
        } else {
            $preview->assertSee('data-oficio-docx-url', false);
        }
    }

    public function test_decano_approval_emails_the_solicitante(): void
    {
        Mail::fake();

        $solicitante = $this->solicitante();
        $decano = $this->usuario(AppRole::Decano, '1312000002');
        $solicitud = $this->solicitudPendienteDecano($solicitante);

        $this->actingAs($decano)
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Solicitud aprobada. Se notificó al solicitante por correo.');

        Mail::assertSent(SolicitudResultadoMail::class, function (SolicitudResultadoMail $mail) use ($solicitante): bool {
            return $mail->hasTo($solicitante->email)
                && $mail->envelope()->subject === 'SAVA: tu solicitud fue aprobada';
        });
    }

    public function test_decano_rejection_emails_the_solicitante(): void
    {
        Mail::fake();

        $solicitante = $this->solicitante();
        $decano = $this->usuario(AppRole::Decano, '1312000003');
        $solicitud = $this->solicitudPendienteDecano($solicitante);

        $this->actingAs($decano)
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '0',
                'observaciones_decano' => 'Fuera de plazo institucional.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Solicitud rechazada. Se notificó al solicitante por correo.');

        Mail::assertSent(SolicitudResultadoMail::class, function (SolicitudResultadoMail $mail) use ($solicitante): bool {
            return $mail->hasTo($solicitante->email)
                && $mail->envelope()->subject === 'SAVA: tu solicitud fue rechazada';
        });
    }

    public function test_secretaria_review_does_not_email_until_the_process_finishes(): void
    {
        Mail::fake();

        $solicitante = $this->solicitante();
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000004');
        $solicitud = $this->solicitudEnRevision($solicitante);

        $this->actingAs($secretaria)
            ->post(route('solicitudes.revisar', $solicitud), [
                'aprobado' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Revisión registrada.');

        Mail::assertNothingSent();
        $this->assertSame(SolicitudEstado::PendienteAprobacionDecano, $solicitud->fresh()->estado);
    }

    public function test_secretaria_rejection_emails_the_solicitante(): void
    {
        Mail::fake();

        $solicitante = $this->solicitante();
        $secretaria = $this->usuario(AppRole::Secretaria, '1312000005');
        $solicitud = $this->solicitudEnRevision($solicitante);

        $this->actingAs($secretaria)
            ->post(route('solicitudes.revisar', $solicitud), [
                'aprobado' => '0',
                'observaciones_secretaria' => 'Falta el justificativo.',
            ])
            ->assertRedirect();

        Mail::assertSent(SolicitudResultadoMail::class, function (SolicitudResultadoMail $mail) use ($solicitante): bool {
            return $mail->hasTo($solicitante->email);
        });
    }

    private function solicitante(): User
    {
        return $this->usuario(AppRole::Administrativo, '1312000001');
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

    private function solicitudPendienteDecano(User $creador): Solicitud
    {
        return $this->crearSolicitud($creador, SolicitudEstado::PendienteAprobacionDecano);
    }

    private function solicitudEnRevision(User $creador): Solicitud
    {
        return $this->crearSolicitud($creador, SolicitudEstado::EnRevisionSecretaria);
    }

    private function crearSolicitud(User $creador, SolicitudEstado $estado): Solicitud
    {
        return Solicitud::query()->create([
            'creado_por' => $creador->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDay()->toDateString(),
            'motivo' => 'Viaje académico de prueba',
            'estado' => $estado,
            'detalle' => ['codigo_tramite' => 'SAVA-TEST-001'],
        ]);
    }
}
