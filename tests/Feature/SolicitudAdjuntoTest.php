<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudOficioFirmadoService;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SolicitudAdjuntoTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjunto_se_sirve_por_ruta_autenticada(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['rol' => AppRole::Docente, 'cedula' => '1312000001', 'carrera' => 'software']);
        $file = UploadedFile::fake()->create('LOGO-ULEAM.png', 100, 'image/png');
        $path = $file->store('justificativos', 'public');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Prueba',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'justificativo_path' => $path,
            'justificativo_nombre' => 'LOGO-ULEAM.png',
            'detalle' => [
                'anexos' => [['path' => $path, 'nombre' => 'LOGO-ULEAM.png']],
            ],
        ]);

        $this->actingAs($user)
            ->get(route('solicitudes.adjunto', ['solicitud' => $solicitud, 'f' => $path]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_oficio_aprobado_superpone_el_sello_en_el_pdf_firmado(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['rol' => AppRole::Docente, 'cedula' => '1312000041', 'carrera' => 'software']);
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 10, 'Sello de APROBADO O RECHAZADO CON FIRMA DEL DECANO');
        $bytes = $pdf->Output('', 'S');

        $path = 'justificativos/oficio-firmado.pdf';
        Storage::disk('local')->put($path, $bytes);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'anexos' => [[
                    'path' => $path,
                    'nombre' => 'oficio-firmado.pdf',
                    'rol' => SolicitudOficioFirmadoService::ROL_ANEXO,
                ]],
            ],
        ]);

        $sinSello = $this->actingAs($user)
            ->get(route('solicitudes.adjunto', ['solicitud' => $solicitud, 'f' => $path]))
            ->assertOk();
        $this->assertSame($bytes, $sinSello->streamedContent());

        $solicitud->update(['estado' => SolicitudEstado::Aprobada]);

        $sellado = $this->actingAs($user)
            ->get(route('solicitudes.adjunto', ['solicitud' => $solicitud, 'f' => $path]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $sellado->getContent());
        $this->assertNotSame($bytes, $sellado->getContent());
    }

    public function test_rechaza_adjunto_con_tipo_no_permitido(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000002',
            'carrera' => 'software',
        ]);

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_permiso' => now()->toDateString(),
                'hora_inicio_permiso' => '08:00',
                'hora_fin_permiso' => '10:00',
                'motivo_permiso' => 'reunion',
                'anexos' => [
                    UploadedFile::fake()->create('malware.exe', 20, 'application/x-msdownload'),
                ],
            ])
            ->assertSessionHasErrors('anexos.0');
    }

    public function test_store_guarda_hasta_varios_anexos_en_un_solo_campo(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000006',
            'carrera' => 'software',
        ]);

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_permiso' => now()->toDateString(),
                'hora_inicio_permiso' => '08:00',
                'hora_fin_permiso' => '10:00',
                'motivo_permiso' => 'reunion',
                'anexos' => [
                    UploadedFile::fake()->create('uno.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('dos.png', 100, 'image/png'),
                ],
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertCount(2, $solicitud->detalle['anexos'] ?? []);
    }

    public function test_enfermedad_exige_justificativo(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000003',
            'carrera' => 'software',
        ]);

        $this->actingAs($user)
            ->from(route('solicitudes.wizard'))
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Enfermedad->value,
                'fecha_inicio' => now()->toDateString(),
                'fecha_fin' => now()->toDateString(),
                'diagnostico' => 'Gripe',
            ])
            ->assertRedirect(route('solicitudes.wizard'))
            ->assertSessionHas('error');

        $this->assertSame(0, Solicitud::query()->count());
    }

    public function test_editar_no_cuenta_eliminaciones_invalidas_en_el_cupo(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000004',
            'carrera' => 'software',
        ]);
        $file = UploadedFile::fake()->create('certificado.png', 20, 'image/png');
        $path = $file->store('justificativos', 'public');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Enfermedad,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Reposo médico',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'justificativo_path' => $path,
            'justificativo_nombre' => 'certificado.png',
            'detalle' => [
                'anexos' => [['path' => $path, 'nombre' => 'certificado.png']],
            ],
        ]);

        $nuevos = [];
        for ($i = 0; $i < 10; $i++) {
            $nuevos[] = UploadedFile::fake()->create("extra-{$i}.png", 20, 'image/png');
        }

        $this->actingAs($user)
            ->from(route('solicitudes.edit', $solicitud))
            ->put(route('solicitudes.update', $solicitud), [
                'tipo' => SolicitudTipo::Enfermedad->value,
                'fecha_inicio' => $solicitud->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => $solicitud->fecha_fin->format('Y-m-d'),
                'motivo' => 'Reposo médico',
                'eliminar_anexos' => ['justificativos/ruta-falsa.png'],
                'anexos' => $nuevos,
            ])
            ->assertRedirect(route('solicitudes.edit', $solicitud))
            ->assertSessionHas('error');
    }

    public function test_editar_agregar_archivo_no_borra_otros_sin_marcar_eliminar(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000007',
            'carrera' => 'software',
        ]);

        $uno = UploadedFile::fake()->create('uno.pdf', 100, 'application/pdf');
        $dos = UploadedFile::fake()->create('dos.pdf', 100, 'application/pdf');
        $pathUno = $uno->store('justificativos', 'local');
        $pathDos = $dos->store('justificativos', 'local');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'justificativo_path' => $pathUno,
            'justificativo_nombre' => 'uno.pdf',
            'detalle' => [
                'anexos' => [
                    ['path' => $pathUno, 'nombre' => 'uno.pdf'],
                    ['path' => $pathDos, 'nombre' => 'dos.pdf'],
                ],
            ],
        ]);

        $nuevo = UploadedFile::fake()->create('tres.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->put(route('solicitudes.update', $solicitud), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_inicio' => $solicitud->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => $solicitud->fecha_fin->format('Y-m-d'),
                'motivo' => 'Permiso',
                'borrador' => 1,
                'anexos' => [$nuevo],
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $solicitud->refresh();
        $paths = array_column($solicitud->detalle['anexos'] ?? [], 'path');

        $this->assertContains($pathUno, $paths);
        $this->assertContains($pathDos, $paths);
        $this->assertCount(3, $paths);
        Storage::disk('local')->assertExists($pathUno);
        Storage::disk('local')->assertExists($pathDos);
    }

    public function test_editar_reemplazo_explicito_quita_solo_el_marcado(): void
    {
        Storage::fake('local');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000008',
            'carrera' => 'software',
        ]);

        $uno = UploadedFile::fake()->create('uno.pdf', 100, 'application/pdf');
        $dos = UploadedFile::fake()->create('dos.pdf', 100, 'application/pdf');
        $pathUno = $uno->store('justificativos', 'local');
        $pathDos = $dos->store('justificativos', 'local');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso',
            'estado' => SolicitudEstado::EnBorrador,
            'justificativo_path' => $pathUno,
            'justificativo_nombre' => 'uno.pdf',
            'detalle' => [
                'anexos' => [
                    ['path' => $pathUno, 'nombre' => 'uno.pdf'],
                    ['path' => $pathDos, 'nombre' => 'dos.pdf'],
                ],
            ],
        ]);

        $reemplazo = UploadedFile::fake()->create('reemplazo.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->put(route('solicitudes.update', $solicitud), [
                'tipo' => SolicitudTipo::Permiso->value,
                'fecha_inicio' => $solicitud->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => $solicitud->fecha_fin->format('Y-m-d'),
                'motivo' => 'Permiso',
                'borrador' => 1,
                'eliminar_anexos' => [$pathUno],
                'anexos' => [$reemplazo],
            ])
            ->assertRedirect(route('solicitudes.show', $solicitud));

        $solicitud->refresh();
        $paths = array_column($solicitud->detalle['anexos'] ?? [], 'path');

        $this->assertNotContains($pathUno, $paths);
        $this->assertContains($pathDos, $paths);
        $this->assertCount(2, $paths);
        Storage::disk('local')->assertMissing($pathUno);
        Storage::disk('local')->assertExists($pathDos);

        $this->actingAs($user)
            ->get(route('solicitudes.show', $solicitud))
            ->assertOk()
            ->assertSee('dos.pdf')
            ->assertSee('reemplazo.pdf')
            ->assertDontSee('uno.pdf');
    }

    public function test_no_permite_quitar_el_justificativo_obligatorio(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1312000005',
            'carrera' => 'software',
        ]);
        $file = UploadedFile::fake()->create('certificado.png', 20, 'image/png');
        $path = $file->store('justificativos', 'public');

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Enfermedad,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Reposo médico',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'justificativo_path' => $path,
            'justificativo_nombre' => 'certificado.png',
            'detalle' => [
                'anexos' => [['path' => $path, 'nombre' => 'certificado.png']],
            ],
        ]);

        $this->actingAs($user)
            ->from(route('solicitudes.edit', $solicitud))
            ->put(route('solicitudes.update', $solicitud), [
                'tipo' => SolicitudTipo::Enfermedad->value,
                'fecha_inicio' => $solicitud->fecha_inicio->format('Y-m-d'),
                'fecha_fin' => $solicitud->fecha_fin->format('Y-m-d'),
                'motivo' => 'Reposo médico',
                'eliminar_anexos' => [$path],
            ])
            ->assertRedirect(route('solicitudes.edit', $solicitud))
            ->assertSessionHas('error');

        $solicitud->refresh();
        $this->assertSame($path, $solicitud->justificativo_path);
    }
}
