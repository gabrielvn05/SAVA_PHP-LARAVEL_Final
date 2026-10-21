<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioPdfService;
use App\Services\SolicitudOficioFirmadoService;
use App\Support\PdfFirmaDigital;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SolicitudOficioFirmadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_docente_redirige_a_pantalla_firmar(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000101',
            'carrera' => 'software',
        ]);

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_permiso' => now()->toDateString(),
                'hora_inicio_permiso' => '08:00',
                'hora_fin_permiso' => '10:00',
                'motivo_permiso' => 'reunion',
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame(SolicitudEstado::EnBorrador, $solicitud->estado);

        $this->actingAs($user)
            ->get(route('solicitudes.firmar', $solicitud))
            ->assertOk()
            ->assertSee('Firmar con certificado .p12', false)
            ->assertSee('Subir documento firmado manualmente', false);
    }

    public function test_enviar_secretaria_sin_firma_vuelve_a_firmar(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000103',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => ['codigo_tramite' => 'FACIVITEC-TEST-ENVIO'],
        ]);

        $this->actingAs($user)
            ->post(route('solicitudes.enviar-secretaria', $solicitud))
            ->assertRedirect(route('solicitudes.firmar', $solicitud));

        $solicitud->refresh();
        $this->assertSame(SolicitudEstado::EnBorrador, $solicitud->estado);
    }

    public function test_subir_manual_y_enviar_secretaria(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000105',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => ['codigo_tramite' => 'FACIVITEC-TEST-MANUAL'],
        ]);

        $pdf = UploadedFile::fake()->create('oficio-firmado.pdf', 200, 'application/pdf');

        $this->actingAs($user)
            ->post(route('solicitudes.subir-oficio-firmado', $solicitud), [
                'oficio_firmado_docente' => $pdf,
            ])
            ->assertRedirect(route('solicitudes.firmar', $solicitud).'#vista-firmado');

        $solicitud->refresh();
        $this->assertNotNull(SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud));

        $this->actingAs($user)
            ->post(route('solicitudes.enviar-secretaria', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $this->assertSame(SolicitudEstado::EnRevisionSecretaria, $solicitud->fresh()->estado);
    }

    public function test_secretaria_ve_el_oficio_firmado_como_documento_principal(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000107',
            'carrera' => 'software',
        ]);
        $secretaria = User::factory()->create([
            'rol' => AppRole::Secretaria,
            'cedula' => '1312000108',
        ]);

        $respaldo = UploadedFile::fake()->create('certificado-medico.pdf', 80, 'application/pdf');
        $respaldoPath = $respaldo->store('justificativos', 'local');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'justificativo_path' => $respaldoPath,
            'justificativo_nombre' => 'certificado-medico.pdf',
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-PRINCIPAL',
                'anexos' => [[
                    'path' => $respaldoPath,
                    'nombre' => 'certificado-medico.pdf',
                ]],
            ],
        ]);

        $this->actingAs($user)
            ->post(route('solicitudes.subir-oficio-firmado', $solicitud), [
                'oficio_firmado_docente' => UploadedFile::fake()->create('oficio-firmado.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('solicitudes.enviar-secretaria', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $firmado = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud->fresh());
        $this->assertNotNull($firmado);

        $this->actingAs($secretaria)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('Oficio firmado', false)
            ->assertSee('oficio-firmado.pdf', false)
            ->assertSee('Descargar oficio firmado', false)
            ->assertSee('Documentos adjuntos', false)
            ->assertSee('certificado-medico.pdf', false)
            ->assertDontSee('Oficio institucional', false)
            ->assertDontSee(route('solicitudes.preview-oficio', $solicitud), false);

        $html = $this->actingAs($secretaria)
            ->get(route('solicitudes.show', $solicitud))
            ->getContent();
        $this->assertSame(2, substr_count((string) $html, 'oficio-firmado.pdf'));

        $this->actingAs($user)
            ->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSee('certificado-medico.pdf', false)
            ->assertDontSee('oficio-firmado.pdf', false);
    }

    public function test_firmar_navegador_y_enviar_secretaria(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000104',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => ['codigo_tramite' => 'FACIVITEC-TEST-NAV'],
        ]);

        $png = UploadedFile::fake()->create('firma.png', 10, 'image/png');

        $this->actingAs($user)
            ->post(route('solicitudes.firmar-navegador', $solicitud), [
                'firma_imagen' => $png,
                'firma_page' => 1,
                'firma_x' => 75,
                'firma_y' => 88,
            ])
            ->assertRedirect(route('solicitudes.firmar', $solicitud).'#vista-firmado');

        $solicitud->refresh();
        $this->assertNotNull(SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud));

        $this->actingAs($user)
            ->post(route('solicitudes.enviar-secretaria', $solicitud))
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $solicitud->refresh();
        $this->assertSame(SolicitudEstado::EnRevisionSecretaria, $solicitud->estado);
    }

    public function test_firma_p12_incrusta_pades_y_el_sello_no_rompe_la_firma(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000199',
            'carrera' => 'software',
        ]);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => ['codigo_tramite' => 'FACVITEC-TEST-P12'],
        ]);

        $base = new Fpdi;
        $base->setPrintHeader(false);
        $base->setPrintFooter(false);
        $base->AddPage();
        $base->SetFont('helvetica', '', 12);
        $base->Cell(0, 10, 'Oficio de prueba');
        $pdfBytes = $base->Output('', 'S');

        $pdfService = $this->createMock(OficioPdfService::class);
        $pdfService->method('generarParaFirma')->willReturn($pdfBytes);

        $p12 = UploadedFile::fake()->createWithContent('docente.p12', $this->certificadoP12('secreta'));
        $service = new SolicitudOficioFirmadoService($pdfService);
        $resultado = $service->firmarConP12EnPosicion($solicitud, $p12, 'secreta', 1, 70, 80, []);

        $this->assertSame('pades', $resultado['firma_docente']['formato'] ?? null);
        $this->assertSame('JUAN PEREZ PRUEBA', $resultado['firma_docente']['titular'] ?? null);

        $path = (string) ($resultado['anexos'][0]['path'] ?? '');
        $guardado = Storage::disk('local')->get($path);
        $this->assertIsString($guardado);
        $this->assertTrue(PdfFirmaDigital::contieneFirmaDigital($guardado));
        $this->assertStringContainsString('adbe.pkcs7.detached', $guardado);
        $this->assertStringContainsString('/FT /Sig', $guardado);
        $this->assertSame(1, preg_match('/\/Contents<([0-9A-Fa-f]+)>/', $guardado, $contenido));
        $pkcs7 = [];
        $this->assertTrue(openssl_pkcs7_read($this->pemPkcs7($contenido[1]), $pkcs7));
        $this->assertNotEmpty($pkcs7);
        $this->assertTrue($this->firmaCubreElPdf($guardado, $contenido[1]));

        $solicitud->update([
            'estado' => SolicitudEstado::Aprobada,
            'detalle' => [
                'codigo_tramite' => 'FACVITEC-TEST-P12',
                'anexos' => $resultado['anexos'],
                'firma_docente' => $resultado['firma_docente'],
            ],
        ]);

        $aprobado = $service->pdfAprobadoConFirmaSolicitante($solicitud->fresh());
        $this->assertIsString($aprobado);
        $this->assertStringStartsWith($guardado, $aprobado);
        $this->assertNotSame($guardado, $aprobado);
        $this->assertStringContainsString('/SelloSAVA', $aprobado);
        $this->assertTrue($this->firmaCubreElPdf($aprobado, $contenido[1]));
    }

    private function certificadoP12(string $password): string
    {
        $config = $this->rutaOpenSsl();
        $opciones = ['config' => $config, 'digest_alg' => 'sha256'];
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => $config,
        ]);
        $this->assertNotFalse($key);

        $csr = openssl_csr_new([
            'commonName' => 'JUAN PEREZ PRUEBA',
            'countryName' => 'EC',
        ], $key, $opciones);
        $this->assertNotFalse($csr);

        $cert = openssl_csr_sign($csr, null, $key, 30, $opciones);
        $this->assertNotFalse($cert);

        $p12 = '';
        $this->assertTrue(openssl_pkcs12_export($cert, $p12, $key, $password, $opciones));

        return $p12;
    }

    private function firmaCubreElPdf(string $pdf, string $hex): bool
    {
        $openssl = $this->binarioOpenSsl();
        if ($openssl === null) {
            return true;
        }

        $this->assertSame(1, preg_match('/\/ByteRange\[0 (\d+) (\d+) (\d+)\]/', $pdf, $rango));
        $datos = substr($pdf, 0, (int) $rango[1]).substr($pdf, (int) $rango[2], (int) $rango[3]);
        $directorio = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sava-verificar-'.bin2hex(random_bytes(4));
        mkdir($directorio);
        $datosPath = $directorio.DIRECTORY_SEPARATOR.'datos.bin';
        $firmaPath = $directorio.DIRECTORY_SEPARATOR.'firma.der';
        $salidaPath = $directorio.DIRECTORY_SEPARATOR.'salida.bin';

        try {
            file_put_contents($datosPath, $datos);
            file_put_contents($firmaPath, $this->derPkcs7($hex));

            $proceso = new Process([
                $openssl,
                'cms', '-verify', '-binary',
                '-inform', 'DER',
                '-in', $firmaPath,
                '-content', $datosPath,
                '-noverify',
                '-out', $salidaPath,
            ]);
            $entorno = getenv();
            if (is_array($entorno)) {
                $entorno['OPENSSL_CONF'] = $this->rutaOpenSsl();
            }
            $proceso->run(null, is_array($entorno) ? $entorno : null);
            $this->assertTrue($proceso->isSuccessful(), trim($proceso->getErrorOutput()."\n".$proceso->getOutput()));

            return true;
        } finally {
            @unlink($datosPath);
            @unlink($firmaPath);
            @unlink($salidaPath);
            @rmdir($directorio);
        }
    }

    private function binarioOpenSsl(): ?string
    {
        $candidatos = [
            'C:/xampp/apache/bin/openssl.exe',
            'C:/xampp/php/extras/openssl/openssl.exe',
            'openssl',
        ];

        foreach ($candidatos as $candidato) {
            if ($candidato !== 'openssl' && is_file($candidato)) {
                return str_replace('\\', '/', $candidato);
            }
        }

        return null;
    }

    private function pemPkcs7(string $hex): string
    {
        return "-----BEGIN PKCS7-----\n".chunk_split(base64_encode($this->derPkcs7($hex)), 64, "\n").'-----END PKCS7-----';
    }

    private function derPkcs7(string $hex): string
    {
        $bin = hex2bin($hex);
        $this->assertNotFalse($bin);
        $this->assertSame(0x30, ord($bin[0]));

        $segundo = ord($bin[1]);
        if ($segundo < 0x80) {
            $longitud = $segundo;
            $cabecera = 2;
        } else {
            $octetos = $segundo & 0x7F;
            $longitud = 0;
            for ($i = 0; $i < $octetos; $i++) {
                $longitud = ($longitud << 8) | ord($bin[2 + $i]);
            }
            $cabecera = 2 + $octetos;
        }

        return substr($bin, 0, $cabecera + $longitud);
    }

    private function rutaOpenSsl(): string
    {
        $candidatos = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/conf/openssl.cnf',
            '/etc/ssl/openssl.cnf',
            '/etc/pki/tls/openssl.cnf',
            '/usr/lib/ssl/openssl.cnf',
        ]);

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return str_replace('\\', '/', $ruta);
            }
        }

        $this->markTestSkipped('No hay openssl.cnf para generar el certificado de prueba.');
    }
}
