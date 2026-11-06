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
use App\Support\SolicitudAdjuntos;
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

        $pdf = UploadedFile::fake()->create('oficio.pdf', 200, 'application/pdf');

        $this->actingAs($user)
            ->post(route('solicitudes.subir-oficio-firmado', $solicitud), [
                'oficio_firmado_docente' => $pdf,
            ])
            ->assertRedirect(route('solicitudes.firmar', $solicitud).'#vista-firmado');

        $solicitud->refresh();
        $firmado = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud);
        $this->assertNotNull($firmado);
        $this->assertSame('FACIVITEC-TEST-MANUAL-firmado.pdf', $firmado['nombre'] ?? null);

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
            ->assertSee('FACIVITEC-TEST-PRINCIPAL-firmado.pdf', false)
            ->assertSee('Descargar oficio firmado', false)
            ->assertSee('Documentos adjuntos', false)
            ->assertSee('certificado-medico.pdf', false)
            ->assertDontSee('Oficio institucional', false)
            ->assertDontSee(route('solicitudes.preview-oficio', $solicitud), false);

        $html = $this->actingAs($secretaria)
            ->get(route('solicitudes.show', $solicitud))
            ->getContent();
        $this->assertSame(2, substr_count((string) $html, 'FACIVITEC-TEST-PRINCIPAL-firmado.pdf'));

        $this->actingAs($user)
            ->get(route('solicitudes.edit', $solicitud))
            ->assertOk()
            ->assertSee('certificado-medico.pdf', false)
            ->assertDontSee('FACIVITEC-TEST-PRINCIPAL-firmado.pdf', false);
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
        $this->assertStringNotContainsString('/SelloSAVA', $guardado);
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
        $this->assertStringContainsString('/SelloSAVA', $aprobado);
        $this->assertTrue($this->firmaCubreElPdf($aprobado, $contenido[1]));
    }

    public function test_oficio_manual_aprobado_recibe_el_sello(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000199',
            'carrera' => 'software',
        ]);

        $base = new Fpdi;
        $base->setPrintHeader(false);
        $base->setPrintFooter(false);
        $base->AddPage();
        $base->SetFont('helvetica', '', 12);
        $base->Cell(0, 10, 'Oficio firmado a mano');
        $pdfBytes = $base->Output('', 'S');
        $path = SolicitudAdjuntos::guardarContenido($pdfBytes, 'pdf');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::Aprobada,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-MANUAL-SELLO',
                'anexos' => [[
                    'path' => $path,
                    'nombre' => 'FACIVITEC-TEST-MANUAL-SELLO-firmado.pdf',
                    'rol' => SolicitudOficioFirmadoService::ROL_ANEXO,
                ]],
                'firma_docente' => ['tipo' => 'manual'],
            ],
        ]);

        $sellado = app(SolicitudOficioFirmadoService::class)->pdfAprobadoConFirmaSolicitante($solicitud);
        $this->assertIsString($sellado);
        $this->assertStringStartsWith($pdfBytes, $sellado);
        $this->assertStringContainsString('/SelloSAVA', $sellado);
        $this->assertStringContainsString("\xFF\xD8\xFF", $sellado);
    }

    public function test_oficio_manual_mantiene_la_firma_y_estampa_el_sello(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000196',
            'carrera' => 'software',
        ]);

        $pdfBytes = $this->pdfConRevisionParcial($this->pdfConFirmaDeTinta());
        $path = SolicitudAdjuntos::guardarContenido($pdfBytes, 'pdf');
        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::Aprobada,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-MANUAL-TINTA',
                'anexos' => [[
                    'path' => $path,
                    'nombre' => 'firmado.pdf',
                    'rol' => SolicitudOficioFirmadoService::ROL_ANEXO,
                ]],
                'firma_docente' => ['tipo' => 'manual'],
            ],
        ]);

        $sellado = app(SolicitudOficioFirmadoService::class)->pdfAprobadoConFirmaSolicitante($solicitud);
        $this->assertIsString($sellado);
        $this->assertStringStartsWith($pdfBytes, $sellado);
        $this->assertStringContainsString('/SelloSAVA', $sellado);
        $this->assertStringContainsString('/Subtype /Ink', $sellado);
        $this->assertStringContainsString('120 150 200 150', $sellado);
    }

    public function test_oficio_manual_no_borra_la_firma_si_no_puede_anexar_el_sello(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000197',
            'carrera' => 'software',
        ]);

        $pdfBytes = "%PDF-1.4\n1 0 obj<< /Subtype /Ink /InkList [[10 10 20 20]] >>endobj\ntrailer<<>>\nstartxref\n9\n%%EOF";
        $path = SolicitudAdjuntos::guardarContenido($pdfBytes, 'pdf');
        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::Aprobada,
            'detalle' => [
                'codigo_tramite' => 'FACIVITEC-TEST-MANUAL-INK',
                'anexos' => [[
                    'path' => $path,
                    'nombre' => 'firmado.pdf',
                    'rol' => SolicitudOficioFirmadoService::ROL_ANEXO,
                ]],
                'firma_docente' => ['tipo' => 'manual'],
            ],
        ]);

        $sellado = app(SolicitudOficioFirmadoService::class)->pdfAprobadoConFirmaSolicitante($solicitud);
        $this->assertSame($pdfBytes, $sellado);
    }

    public function test_al_aprobar_el_sello_se_anexa_sin_reescribir_la_firma(): void
    {
        Storage::fake('local');

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
            'estado' => SolicitudEstado::Aprobada,
            'detalle' => ['codigo_tramite' => 'FACVITEC-TEST-SELLO'],
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

        $path = (string) ($resultado['anexos'][0]['path'] ?? '');
        $guardado = Storage::disk('local')->get($path);
        $this->assertIsString($guardado);
        $this->assertArrayNotHasKey('incluye_sello', $resultado['firma_docente']);
        $this->assertStringNotContainsString('/SelloSAVA', $guardado);
        $this->assertSame(1, preg_match('/\/Contents<([0-9A-Fa-f]+)>/', $guardado, $contenido));
        $this->assertTrue($this->firmaCubreElPdf($guardado, $contenido[1]));

        $solicitud->update([
            'detalle' => [
                'codigo_tramite' => 'FACVITEC-TEST-SELLO',
                'anexos' => $resultado['anexos'],
                'firma_docente' => $resultado['firma_docente'],
            ],
        ]);

        $aprobado = $service->pdfAprobadoConFirmaSolicitante($solicitud->fresh());
        $this->assertIsString($aprobado);
        $this->assertStringStartsWith($guardado, $aprobado);
        $this->assertStringContainsString('/SelloSAVA', $aprobado);
        $this->assertTrue($this->firmaCubreElPdf($aprobado, $contenido[1]));
    }

    private function pdfConFirmaDeTinta(): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [6 0 R] >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            6 => '<< /Type /Annot /Subtype /Ink /Rect [100 100 220 180] /InkList [[120 150 200 150]] /C [1 0 0] /BS << /W 3 >> >>',
        ];
        $flujo = "BT /F1 24 Tf 72 700 Td (Oficio) Tj ET\n";
        $objetos[4] = "<< /Length ".strlen($flujo)." >>\nstream\n".$flujo.'endstream';
        ksort($objetos);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $numero => $cuerpo) {
            $offsets[$numero] = strlen($pdf);
            $pdf .= $numero." 0 obj\n".$cuerpo."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 7\n";
        $pdf .= "0000000000 65535 f \n";
        for ($numero = 1; $numero <= 6; $numero++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$numero]);
        }
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfConRevisionParcial(string $pdf): string
    {
        if (! preg_match('/startxref\s+(\d+)/', $pdf, $coincidencia)) {
            $this->fail('El PDF de prueba no tiene startxref.');
        }
        if (! str_ends_with($pdf, "\n")) {
            $pdf .= "\n";
        }
        $inicio = strlen($pdf);
        $pdf .= "xref\n0 1\n0000000000 65535 f \n";
        $pdf .= "trailer\n<< /Size 7 /Root 1 0 R /Prev {$coincidencia[1]} >>\n";
        $pdf .= "startxref\n{$inicio}\n%%EOF\n";

        return $pdf;
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
