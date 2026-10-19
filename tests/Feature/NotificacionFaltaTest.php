<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\NotificacionFalta;
use App\Models\User;
use App\Models\UserCapability;
use App\Mail\NotificacionFaltaColaboradorMail;
use App\Services\NotificacionFaltaDocxService;
use App\Services\OficioDatosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use ZipArchive;

class NotificacionFaltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_decano_puede_registrar_notificacion_y_descargar_docx(): void
    {
        Mail::fake();

        $decano = User::factory()->create(['rol' => AppRole::Decano, 'activo' => true]);
        $colaborador = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000777',
            'carrera' => 'software',
            'nombres' => 'Luis',
            'apellidos' => 'Pérez',
            'email' => 'luis.perez@uleam.edu.ec',
        ]);

        $this->actingAs($decano)
            ->post(route('notificaciones-faltas.store'), [
                'destinatario_id' => $colaborador->id,
                'fecha_falta' => '2026-09-10',
                'tipo_marcacion' => 'entrada',
            ])
            ->assertRedirect(route('notificaciones-faltas.index'));

        $notificacion = NotificacionFalta::query()->first();
        $this->assertNotNull($notificacion);
        $this->assertSame('FACIVITEC-ADM-0001', $notificacion->codigo_oficio);
        $this->assertFalse($notificacion->emitido_delegado);

        Mail::assertSent(NotificacionFaltaColaboradorMail::class, function (NotificacionFaltaColaboradorMail $mail) use ($colaborador): bool {
            return $mail->hasTo($colaborador->email);
        });

        $this->actingAs($decano)
            ->get(route('notificaciones-faltas.descargar', $notificacion))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $vista = $this->actingAs($decano)
            ->get(route('notificaciones-faltas.preview', $notificacion));

        $vista->assertOk();
        $tipo = (string) $vista->headers->get('content-type');
        if (str_contains($tipo, 'application/pdf')) {
            $this->assertStringStartsWith('%PDF', $vista->getContent());
        } else {
            $vista->assertSee('data-oficio-docx-url', false)
                ->assertSee($notificacion->codigo_oficio, false);
        }
    }

    public function test_secretaria_sin_delegacion_no_accede(): void
    {
        $secretaria = User::factory()->create(['rol' => AppRole::Secretaria]);

        $this->actingAs($secretaria)
            ->get(route('notificaciones-faltas.index'))
            ->assertForbidden();
    }

    public function test_secretaria_delegada_emite_como_decano_s(): void
    {
        $decano = User::factory()->create([
            'rol' => AppRole::Decano,
            'activo' => true,
            'nombres' => 'Alex',
            'apellidos' => 'Santamaria',
        ]);
        $secretaria = User::factory()->create(['rol' => AppRole::Secretaria]);
        UserCapability::query()->create([
            'user_id' => $secretaria->id,
            'capability' => CapabilityType::EmitirNotificacionesFaltas,
            'otorgado_por' => $decano->id,
            'created_at' => now(),
        ]);
        $colaborador = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000888',
        ]);

        $this->actingAs($secretaria)
            ->post(route('notificaciones-faltas.store'), [
                'destinatario_id' => $colaborador->id,
                'fecha_falta' => '2026-09-11',
                'tipo_marcacion' => 'salida',
            ])
            ->assertRedirect();

        $notificacion = NotificacionFalta::query()->first();
        $this->assertTrue($notificacion->emitido_delegado);
        $this->assertSame($decano->id, $notificacion->decano_id);
        $this->assertSame($secretaria->id, $notificacion->emitido_por_id);

        $this->actingAs($secretaria)
            ->get(route('notificaciones-faltas.show', $notificacion))
            ->assertOk()
            ->assertSee('Salió del usuario de '.$decano->nombreCompleto(), false)
            ->assertSee('Decano de FACIVITEC', false);

        $binary = app(NotificacionFaltaDocxService::class)->generar($notificacion);
        $temp = tempnam(sys_get_temp_dir(), 'notif-delegada-');
        file_put_contents($temp, $binary);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temp));
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        @unlink($temp);

        $this->assertStringContainsString($decano->nombreCompleto(), $xml);
        $this->assertStringNotContainsString('Decano (s)', $xml);
    }

    public function test_genera_docx_con_datos_del_colaborador(): void
    {
        $decano = User::factory()->create([
            'rol' => AppRole::Decano,
            'nombres' => 'Alex',
            'apellidos' => 'Santamaria',
            'email' => 'decano@uleam.edu.ec',
        ]);
        $colaborador = User::factory()->create([
            'rol' => AppRole::Docente,
            'nombres' => 'María',
            'apellidos' => 'López',
            'carrera' => 'software',
        ]);

        $notificacion = NotificacionFalta::query()->create([
            'destinatario_id' => $colaborador->id,
            'decano_id' => $decano->id,
            'emitido_por_id' => $decano->id,
            'fecha_falta' => '2026-09-15',
            'tipo_marcacion' => 'entrada_salida',
            'codigo_oficio' => 'FACIVITEC-ADM-0005',
            'emitido_delegado' => false,
            'tiene_justificacion_aprobada' => false,
        ]);

        $binary = app(NotificacionFaltaDocxService::class)->generar($notificacion);
        $temp = tempnam(sys_get_temp_dir(), 'notif-test-');
        file_put_contents($temp, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temp));
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        @unlink($temp);

        $this->assertStringContainsString('María López', $xml);
        $this->assertStringContainsString('FACIVITEC-ADM-0005', $xml);
        $this->assertStringContainsString('Alex Santamaria', $xml);
        $this->assertStringContainsString('Decano', $xml);
        $this->assertStringContainsString(OficioDatosService::FACULTAD_NOMBRE, $xml);
        $this->assertStringContainsString(OficioDatosService::fechaLugarGeneracion(), $xml);
        $this->assertStringContainsString('decano@uleam.edu.ec', $xml);
        $this->assertStringNotContainsString('[facultad]', $xml);
        $this->assertStringNotContainsString('[Nombre del decano]', $xml);
        $this->assertStringNotContainsString('15 de septiembre del 2026', $xml);
        $this->assertStringNotContainsString('[Nombre completo del colaborador]', $xml);
    }
}
