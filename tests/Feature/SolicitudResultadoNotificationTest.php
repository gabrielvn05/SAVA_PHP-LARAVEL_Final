<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Mail\SolicitudResultadoMail;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioCorreoAdjuntoService;
use App\Services\OficioPdfService;
use App\Services\SolicitudOficioFirmadoService;
use App\Support\SolicitudAdjuntos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
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
            ->assertSee('data-doc-viewer', false)
            ->assertSee('Abrir en el visor del teléfono', false)
            ->assertSee(route('solicitudes.preview-oficio', $solicitud), false);

        $preview = $this->actingAs($user)
            ->get(route('solicitudes.preview-oficio', $solicitud))
            ->assertOk();

        $contentType = (string) $preview->headers->get('content-type');
        if (str_contains($contentType, 'pdf')) {
            $preview->assertHeader('content-type', 'application/pdf');
        } else {
            $preview->assertSee('data-oficio-docx-url', false);
            $preview->assertDontSee('No se pudo cargar la vista previa del oficio', false);
        }
    }

    public function test_correo_aprobacion_adjunta_pdf_firmado_cuando_existe(): void
    {
        $this->mock(OficioPdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('invalidarCache')->andReturnNull();
            $mock->shouldReceive('generar')->andReturn('%PDF-1.4 contenido-prueba');
        });

        Mail::fake();

        $solicitante = $this->solicitante();
        $decano = $this->usuario(AppRole::Decano, '1312000010');
        $solicitud = $this->solicitudPendienteDecano($solicitante);

        $this->actingAs($decano)
            ->post(route('solicitudes.aprobar', $solicitud), [
                'aprobado' => '1',
            ])
            ->assertRedirect();

        Mail::assertSent(SolicitudResultadoMail::class, function (SolicitudResultadoMail $mail): bool {
            return count($mail->attachments()) === 1
                && str_ends_with($mail->attachments()[0]->as, '-firmado.pdf');
        });
    }

    public function test_correo_aprobacion_conserva_la_firma_del_solicitante(): void
    {
        Storage::fake('local');

        $tcpdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $tcpdf->setPrintHeader(false);
        $tcpdf->setPrintFooter(false);
        $tcpdf->AddPage();
        $tcpdf->SetFont('helvetica', '', 12);
        $tcpdf->Text(20, 40, 'FIRMA-SOLICITANTE-XYZ');
        $binario = $tcpdf->Output('', 'S');

        $path = SolicitudAdjuntos::guardarContenido($binario, 'pdf');
        $solicitud = $this->crearSolicitud($this->solicitante(), SolicitudEstado::Aprobada);
        $detalle = $solicitud->detalle ?? [];
        $detalle['anexos'] = [[
            'path' => $path,
            'nombre' => 'oficio-firmado-navegador.pdf',
            'rol' => SolicitudOficioFirmadoService::ROL_ANEXO,
        ]];
        $solicitud->update(['detalle' => $detalle]);

        $this->mock(OficioPdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generar')->never();
        });

        $adjunto = app(OficioCorreoAdjuntoService::class)->resolver($solicitud->fresh());

        $this->assertSame('pdf', $adjunto->formato);
        $this->assertCount(1, $adjunto->attachments);
        $contenido = $this->contenidoAdjunto($adjunto->attachments[0]);
        $this->assertStringStartsWith('%PDF', $contenido);
        $this->assertNotSame($binario, $contenido);
        $this->assertStringContainsString("\xFF\xD8\xFF", $contenido);
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

    private function contenidoAdjunto(\Illuminate\Mail\Mailables\Attachment $attachment): string
    {
        $datos = $attachment->attachWith(
            fn () => null,
            fn (callable $data) => $data(),
        );

        return is_string($datos) ? $datos : '';
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
