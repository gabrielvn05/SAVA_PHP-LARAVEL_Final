<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioDocxService;
use App\Support\OficioSelloAutorizado;
use App\Support\OficioSelloFaltanteException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $this->assertStringContainsString('Correo Institucional:', $xml);
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

        $this->actingAs($user)
            ->get(route('solicitudes.oficio-descargar', $solicitud))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }
}
