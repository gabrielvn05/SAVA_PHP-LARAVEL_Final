<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

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

    public function test_excel_y_csv_siguen_la_matriz_de_novedades(): void
    {
        $secretaria = User::factory()->create(['rol' => AppRole::Secretaria]);
        $docente = User::factory()->create([
            'rol' => AppRole::Docente,
            'nombres' => 'Miguel',
            'apellidos' => 'Zamora',
            'cedula' => '1314977255',
        ]);
        $administrativo = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'nombres' => 'Ana',
            'apellidos' => 'Perez',
            'cedula' => '1312000991',
        ]);
        $mantenimiento = User::factory()->create([
            'rol' => AppRole::Mantenimiento,
            'nombres' => 'Luis',
            'apellidos' => 'Mera',
            'cedula' => '1312000881',
        ]);

        $this->solicitud($docente, SolicitudTipo::Permiso, SolicitudEstado::Aprobada, '2026-10-07', '2026-10-07', 'FACIVITEC-PER-REU', [
            'motivo_permiso' => 'reunion',
            'hora_inicio_permiso' => '08:00',
            'hora_fin_permiso' => '10:30',
        ]);
        $this->solicitud($docente, SolicitudTipo::Justificacion, SolicitudEstado::Aprobada, '2026-10-08', '2026-10-08', 'FACIVITEC-ATR-01', [
            'hora_llegada_establecida' => '08:00',
            'hora_llegada_real' => '08:20',
        ]);
        $this->solicitud($administrativo, SolicitudTipo::Enfermedad, SolicitudEstado::Aprobada, '2026-10-01', '2026-10-03', 'FACIVITEC-ENF-01');
        $this->solicitud($mantenimiento, SolicitudTipo::Viaje, SolicitudEstado::EnRevisionSecretaria, '2026-10-10', '2026-10-10', 'FACIVITEC-VIA-01');

        $response = $this->actingAs($secretaria)->get(route('reportes.export', [
            'format' => 'xlsx',
            'desde' => '2026-10-01',
            'hasta' => '2026-10-31',
        ]));

        $response->assertOk();
        $response->assertDownload();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);

        $origen = $response->baseResponse->getFile()->getPathname();
        $copia = tempnam(sys_get_temp_dir(), 'matriz-test-');
        $this->assertNotFalse($copia);
        copy($origen, $copia);

        $hoja = $this->celdasHoja($copia, 'xl/worksheets/sheet2.xml');
        $textos = $this->textosCompartidos($copia);
        @unlink($copia);

        $this->assertStringContainsString('FACULTAD DE CIENCIAS DE LA VIDA Y LA TECNOLOGÍA (FACIVITEC)', $textos);
        $this->assertStringContainsString('PERIODO : DEL 01 AL 31 DE OCTUBRE DE 2026', $textos);
        $this->assertStringContainsString('Cargo del Jefe Inmediato', $textos);
        $this->assertStringContainsString('LINEAMIENTOS PARA EL LLENADO', $textos);

        $permiso = $this->filaPorDocumento($hoja, 'FACIVITEC-PER-REU');
        $this->assertSame('1314977255', $permiso['B']);
        $this->assertSame('Zamora Miguel', $permiso['C']);
        $this->assertSame('Docente', $permiso['D']);
        $this->assertSame('docente', $permiso['E']);
        $this->assertSame('0', $permiso['F']);
        $this->assertSame('2', $permiso['G']);
        $this->assertSame('30', $permiso['H']);
        $this->assertSame('X', $permiso['J']);
        $this->assertSame('', $permiso['K']);
        $this->assertSame('', $permiso['Q']);
        $this->assertSame('07/10/2026', $permiso['S']);

        $atraso = $this->filaPorDocumento($hoja, 'FACIVITEC-ATR-01');
        $this->assertSame('0', $atraso['F']);
        $this->assertSame('0', $atraso['G']);
        $this->assertSame('20', $atraso['H']);
        $this->assertSame('X', $atraso['N']);
        $this->assertSame('', $atraso['Q']);

        $enfermedad = $this->filaPorDocumento($hoja, 'FACIVITEC-ENF-01');
        $this->assertSame('Perez Ana', $enfermedad['C']);
        $this->assertSame('Administrativo', $enfermedad['D']);
        $this->assertSame('personal tecnológico', $enfermedad['E']);
        $this->assertSame('3', $enfermedad['F']);
        $this->assertSame('0', $enfermedad['G']);
        $this->assertSame('0', $enfermedad['H']);
        $this->assertSame('X', $enfermedad['Q']);
        $this->assertSame('', $enfermedad['I']);

        $viaje = $this->filaPorDocumento($hoja, 'FACIVITEC-VIA-01');
        $this->assertSame('Código de trabajo', $viaje['D']);
        $this->assertSame('mantenimiento', $viaje['E']);
        $this->assertSame('1', $viaje['F']);
        $this->assertSame('', $viaje['Q']);
        $this->assertSame('', $viaje['R']);

        $csv = $this->actingAs($secretaria)->get(route('reportes.export', ['format' => 'csv']));
        $csv->assertOk();
        $contenido = $csv->streamedContent();
        $this->assertStringContainsString('LICENCIA Y/O COMISION SERVICIOS CON RMU', $contenido);
        $this->assertStringContainsString('FACIVITEC-PER-REU', $contenido);
        $this->assertStringContainsString('personal tecnológico', $contenido);
    }

    /**
     * @param  array<string, string>  $detalle
     */
    private function solicitud(
        User $creador,
        SolicitudTipo $tipo,
        SolicitudEstado $estado,
        string $desde,
        string $hasta,
        string $codigo,
        array $detalle = [],
    ): void {
        Solicitud::query()->create([
            'creado_por' => $creador->id,
            'tipo' => $tipo,
            'fecha_inicio' => $desde,
            'fecha_fin' => $hasta,
            'motivo' => 'Novedad de prueba',
            'estado' => $estado,
            'detalle' => ['codigo_tramite' => $codigo, ...$detalle],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function celdasHoja(string $path, string $hoja): array
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName($hoja);
        $zip->close();
        $this->assertIsString($xml);

        $documento = new \DOMDocument;
        $documento->loadXML($xml);
        $celdas = [];
        foreach ($documento->getElementsByTagName('c') as $celda) {
            $ref = $celda->getAttribute('r');
            $valor = '';
            if ($celda->getAttribute('t') === 'inlineStr') {
                $valor = $celda->textContent;
            } elseif ($celda->getElementsByTagName('v')->length > 0) {
                $valor = $celda->getElementsByTagName('v')->item(0)?->textContent ?? '';
            }
            $celdas[$ref] = $valor;
        }

        return $celdas;
    }

    private function textosCompartidos(string $path): string
    {
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        return is_string($xml) ? $xml : '';
    }

    /**
     * @param  array<string, string>  $celdas
     * @return array<string, string>
     */
    private function filaPorDocumento(array $celdas, string $codigo): array
    {
        $fila = null;
        foreach ($celdas as $ref => $valor) {
            if (preg_match('/^U(\d+)$/', $ref, $coincide) === 1 && $valor === $codigo) {
                $fila = $coincide[1];
                break;
            }
        }

        $this->assertNotNull($fila, "No está el documento {$codigo}");

        $columnas = [];
        foreach (range('A', 'V') as $columna) {
            $columnas[$columna] = $celdas[$columna.$fila] ?? '';
        }

        return $columnas;
    }
}
