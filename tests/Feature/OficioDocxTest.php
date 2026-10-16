<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioDocxService;
use App\Services\OficioPdfService;
use App\Support\OficioSelloAutorizado;
use App\Support\OficioSelloFaltanteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

class OficioDocxTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_docx_desde_plantilla_viaje(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'nombres' => 'Ana',
            'apellidos' => 'García',
            'cedula' => '1312000099',
            'carrera' => 'software',
            'email' => 'e1314977255@live.uleam.edu.ec',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(2)->toDateString(),
            'motivo' => 'Congreso internacional',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-15092026-VIA-AG',
                'tipo_viaje_evento' => 'congreso_expositor',
                'nombre_evento' => 'Simposio SAVA',
                'lugar_evento' => 'Quito, Ecuador',
                'fecha_evento_desde' => '2026-09-15',
                'fecha_evento_hasta' => '2026-09-20',
            ],
        ]);

        $binary = app(OficioDocxService::class)->generar($solicitud);
        $this->assertNotSame('', $binary);

        $temp = tempnam(sys_get_temp_dir(), 'oficio-test-');
        file_put_contents($temp, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temp));
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();
        @unlink($temp);

        $this->assertStringContainsString('Ana García', $xml);
        $this->assertStringContainsString('FACIVITEC-15092026-VIA-AG', $xml);
        $this->assertStringContainsString('Simposio SAVA', $xml);
        $this->assertStringContainsString('desde el 15 hasta el 20 de septiembre de 2026', $xml);
        $this->assertStringNotContainsString('[Nombre completo del docente]', $xml);
        $this->assertStringNotContainsString('____', $xml);
        $this->assertStringContainsString('Fecha de generación del documento', $xml);

        $this->assertStringContainsString("Correo\u{00A0}Institucional:\u{00A0}".$user->email, $xml);
        $this->assertStringContainsString('<w:gridCol w:w="8838"/>', $xml);
        $this->assertMatchesRegularExpression(
            '/<w:jc w:val="left"\/>(?:(?!<\/w:p>).)*Correo\x{00A0}Institucional:/su',
            $xml,
        );
        $this->assertStringNotContainsString('[Correo]', $xml);
        $this->assertStringContainsString('visita t', $xml);
        $this->assertStringNotContainsString('[Tipo de visita de campo]', $xml);
    }

    public function test_docx_aprobado_incluye_sello_autorizado(): void
    {
        $docente = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000098',
            'carrera' => 'software',
        ]);
        $decano = User::factory()->create([
            'rol' => AppRole::Decano,
            'nombres' => 'María',
            'apellidos' => 'Decano',
            'cedula' => '1312000097',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $docente->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso de prueba',
            'estado' => SolicitudEstado::Aprobada,
            'firmado_por' => $decano->id,
            'detalle' => ['codigo_tramite' => 'FACIVITEC-TEST-SELLO'],
        ]);

        $binary = app(OficioDocxService::class)->generar($solicitud, $decano);
        $temp = tempnam(sys_get_temp_dir(), 'oficio-sello-');
        file_put_contents($temp, $binary);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($temp));
        $xml = $zip->getFromName('word/document.xml') ?: '';
        $this->assertNotFalse($zip->locateName('word/media/sello-autorizado.jpg'));
        $zip->close();
        @unlink($temp);

        $this->assertStringContainsString('Sello AUTORIZADO', $xml);
        $this->assertStringNotContainsString('Sello de APROBADO O RECHAZADO CON FIRMA DEL DECANO', $xml);
    }

    public function test_oficio_aprobado_falla_si_falta_el_sello(): void
    {
        $docente = User::factory()->create(['rol' => AppRole::Administrativo]);
        $decano = User::factory()->create(['rol' => AppRole::Decano]);
        $solicitud = Solicitud::query()->create([
            'creado_por' => $docente->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso de prueba',
            'estado' => SolicitudEstado::Aprobada,
            'firmado_por' => $decano->id,
            'detalle' => ['codigo_tramite' => 'FACIVITEC-TEST-SELLO-FALTA'],
        ]);

        OficioSelloAutorizado::$assetPathForTests = storage_path('app/missing-sello-autorizado.jpg');
        try {
            app(OficioDocxService::class)->generar($solicitud, $decano);
            $this->fail('Debió fallar al faltar el sello de autorizado.');
        } catch (OficioSelloFaltanteException $e) {
            $this->assertStringContainsString('sello', mb_strtolower($e->getMessage()));
        } finally {
            OficioSelloAutorizado::$assetPathForTests = null;
        }
    }

    public function test_ruta_descarga_oficio_docx(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000088',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Enfermedad,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Reposo médico',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-15092026-MED-XX',
                'diagnostico' => 'Gripe',
                'medico_tratante' => 'Dr. Test',
                'institucion_medica_tipo' => 'IESS',
            ],
        ]);

        $this->mock(OficioPdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generar')->andReturn(null);
            $mock->shouldReceive('usaVistaPdf')->andReturn(false);
            $mock->shouldReceive('puedeConvertir')->andReturn(false);
            $mock->shouldReceive('puedeMostrarPdfEnVisor')->andReturn(false);
            $mock->shouldReceive('invalidarCache')->zeroOrMoreTimes();
        });

        $this->actingAs($user)
            ->get(route('solicitudes.oficio-descargar', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_preview_html_muestra_el_oficio_cuando_no_hay_pdf(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'nombres' => 'Gabriel',
            'apellidos' => 'Velez',
            'cedula' => '1312000085',
            'carrera' => 'software',
        ]);
        $decano = User::factory()->create([
            'rol' => AppRole::Decano,
            'nombres' => 'Maria',
            'apellidos' => 'Decano',
            'cedula' => '1312000084',
            'carrera' => 'software',
        ]);
        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Calamidad familiar',
            'estado' => SolicitudEstado::Aprobada,
            'firmado_por' => $decano->id,
            'fecha_firma' => now(),
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-14102026-PER-CA',
                'fecha_permiso' => now()->toDateString(),
                'hora_inicio_permiso' => '08:00',
                'hora_fin_permiso' => '12:00',
            ],
        ]);

        $this->mock(OficioPdfService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generar')->andReturn(null);
            $mock->shouldReceive('usaVistaPdf')->andReturn(false);
            $mock->shouldReceive('puedeConvertir')->andReturn(false);
            $mock->shouldReceive('puedeMostrarPdfEnVisor')->andReturn(false);
            $mock->shouldReceive('invalidarCache')->zeroOrMoreTimes();
        });

        $this->actingAs($user)
            ->get(route('solicitudes.preview-oficio', $solicitud))
            ->assertOk()
            ->assertSee('data-oficio-docx-url', false)
            ->assertSee(route('solicitudes.oficio-documento', $solicitud), false)
            ->assertSee('docx-preview.min.js', false)
            ->assertDontSee('Vista previa aproximada');

        $this->actingAs($user)
            ->get(route('solicitudes.preview-oficio', ['solicitud' => $solicitud, 'html' => 1]))
            ->assertOk()
            ->assertSee('OFICIO N°', false)
            ->assertSee('FACIVITEC-14102026-PER-CA')
            ->assertSee('Gabriel Velez')
            ->assertSee('sello-autorizado.jpg', false)
            ->assertSee('Sello AUTORIZADO');

        $this->actingAs($user)
            ->get(route('solicitudes.oficio-documento', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_oficio_aprobado_se_previsualiza_y_descarga_como_pdf_firmado(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000087',
            'carrera' => 'software',
        ]);
        $decano = User::factory()->create([
            'rol' => AppRole::Decano,
            'cedula' => '1312000086',
            'carrera' => 'software',
        ]);
        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso firmado',
            'estado' => SolicitudEstado::Aprobada,
            'firmado_por' => $decano->id,
            'fecha_firma' => now(),
            'detalle' => ['codigo_tramite' => 'FACIVITEC-07102026-PER-MZ'],
        ]);

        $pdf = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF";
        $this->mock(OficioPdfService::class, function (MockInterface $mock) use ($pdf): void {
            $mock->shouldReceive('generar')->andReturn($pdf);
            $mock->shouldReceive('usaVistaPdf')->andReturn(true);
            $mock->shouldReceive('puedeConvertir')->andReturn(true);
            $mock->shouldReceive('puedeMostrarPdfEnVisor')->andReturn(true);
            $mock->shouldReceive('invalidarCache')->zeroOrMoreTimes();
        });

        $this->actingAs($user)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Oficio firmado')
            ->assertSee('Descargar PDF firmado')
            ->assertSee(route('solicitudes.oficio-descargar', $solicitud), false);

        $this->actingAs($user)
            ->get(route('solicitudes.preview-oficio', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename="FACIVITEC-07102026-PER-MZ-firmado.pdf"');

        $this->actingAs($user)
            ->get(route('solicitudes.oficio-descargar', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="FACIVITEC-07102026-PER-MZ-firmado.pdf"')
            ->assertSee('%PDF-1.4', false);
    }
}
