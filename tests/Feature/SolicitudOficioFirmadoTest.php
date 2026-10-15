<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\SolicitudOficioFirmadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
