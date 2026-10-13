<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
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
}
