<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretaria_puede_exportar_reporte_xlsx(): void
    {
        $secretaria = User::factory()->create(['rol' => AppRole::Secretaria]);
        $docente = User::factory()->create(['rol' => AppRole::Docente]);

        Solicitud::query()->create([
            'creado_por' => $docente->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Permiso personal',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => ['codigo_tramite' => 'FACIVITEC-TEST-PER-01'],
        ]);

        $response = $this->actingAs($secretaria)
            ->get(route('reportes.export', ['format' => 'xlsx']));

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_secretaria_puede_exportar_reporte_csv(): void
    {
        $secretaria = User::factory()->create(['rol' => AppRole::Secretaria]);

        $response = $this->actingAs($secretaria)
            ->get(route('reportes.export', ['format' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
