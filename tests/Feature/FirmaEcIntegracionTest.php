<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\FirmaEcEnvio;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioPdfService;
use App\Services\SolicitudOficioFirmadoService;
use App\Support\PdfFirmaDigital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FirmaEcIntegracionTest extends TestCase
{
    use RefreshDatabase;

    public function test_inicia_firmaec_y_el_callback_guarda_el_pdf_sin_reescribirlo(): void
    {
        Storage::fake('local');
        config([
            'firmaec.api_key' => 'pruebas',
            'firmaec.sistema' => 'pruebas',
            'firmaec.base_url' => 'https://impws.firmadigital.gob.ec',
            'firmaec.pruebas' => true,
            'firmaec.callback_key' => 'pruebas',
            'firmaec.estampado' => 'information2',
        ]);

        $pdf = "%PDF-1.4\nOficio\n%%EOF";
        $this->mock(OficioPdfService::class, function ($mock) use ($pdf): void {
            $mock->shouldReceive('generarParaFirma')->once()->andReturn($pdf);
        });

        Http::fake([
            'https://impws.firmadigital.gob.ec/servicio/documentos' => Http::response('aaa.bbb.ccc', 201),
        ]);

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000198',
            'carrera' => 'software',
        ]);
        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => ['codigo_tramite' => 'FACVITEC-FIRMAEC'],
        ]);

        $inicio = $this->actingAs($user)
            ->postJson(route('solicitudes.firmar-firmaec', $solicitud), [
                'tipo_certificado' => 2,
                'firma_page' => 1,
                'firma_x' => 70,
                'firma_y' => 80,
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'pendiente');

        $enlace = (string) $inicio->json('enlace');
        $this->assertStringStartsWith('firmaec://pruebas/firmar?', $enlace);
        $this->assertStringContainsString('token=aaa.bbb.ccc', $enlace);
        $this->assertStringContainsString('estampado=information2', $enlace);
        $this->assertStringContainsString('pre=true', $enlace);
        $this->assertStringContainsString('tipo_certificado=2', $enlace);

        Http::assertSent(function ($request) use ($user, $pdf): bool {
            $documento = base64_decode((string) data_get($request->data(), 'documentos.0.documento'), true);

            return str_ends_with($request->url(), '/servicio/documentos')
                && $request->hasHeader('X-API-KEY', 'pruebas')
                && data_get($request->data(), 'sistema') === 'pruebas'
                && data_get($request->data(), 'cedula') === $user->cedula
                && $documento === $pdf;
        });

        $envio = FirmaEcEnvio::query()->first();
        $this->assertNotNull($envio);
        $firmado = "%PDF-1.4\n/Type /Sig /ByteRange /ETSI.CAdES.detached\n%%EOF";

        $this->post(route('firmaec.grabar'), [
            'cedula' => $user->cedula,
            'nombreDocumento' => $envio->nombre_documento,
            'archivo' => base64_encode($firmado),
            'firmasValidas' => true,
            'integridadDocumento' => true,
            'error' => 'null',
            'certificado' => [[
                'emitidoPara' => 'JUAN PEREZ',
                'serial' => '999',
                'entidadCertificadora' => 'Security Data',
                'fechaFirma' => '2026-10-20T20:00:00-05:00',
            ]],
        ], ['X-API-KEY' => 'pruebas'])
            ->assertOk()
            ->assertSee('OK');

        $this->assertSame('firmado', $envio->fresh()->estado);
        $anexo = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud->fresh());
        $this->assertNotNull($anexo);
        $guardado = Storage::disk('local')->get((string) $anexo['path']);
        $this->assertSame($firmado, $guardado);
        $this->assertTrue(PdfFirmaDigital::contieneFirmaDigital((string) $guardado));

        $solicitud->update(['estado' => SolicitudEstado::Aprobada]);
        $this->assertNull(app(SolicitudOficioFirmadoService::class)->pdfAprobadoConFirmaSolicitante($solicitud->fresh()));
    }

    public function test_callback_rechaza_una_clave_distinta(): void
    {
        config(['firmaec.api_key' => 'pruebas', 'firmaec.callback_key' => 'pruebas']);

        $this->post(route('firmaec.grabar'), [
            'cedula' => '1312000198',
            'nombreDocumento' => 'no-existe.pdf',
            'archivo' => base64_encode('%PDF-1.4'),
            'firmasValidas' => true,
            'integridadDocumento' => true,
        ], ['X-API-KEY' => 'otra'])
            ->assertForbidden();
    }
}
