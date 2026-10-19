<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Support\MatrizNovedades;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteExportService
{
    public function __construct(private readonly MatrizNovedadesXlsxWriter $matriz) {}

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    public function exportGeneral(
        Collection $solicitudes,
        string $format = 'xlsx',
        ?CarbonInterface $desde = null,
        ?CarbonInterface $hasta = null,
    ): StreamedResponse|BinaryFileResponse {
        return match ($format) {
            'csv' => $this->exportCsv($solicitudes),
            default => $this->exportXlsx($solicitudes, $desde, $hasta),
        };
    }

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    private function exportCsv(Collection $solicitudes): StreamedResponse
    {
        $filename = $this->baseFilename().'.csv';

        return response()->streamDownload(function () use ($solicitudes): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, MatrizNovedades::encabezadosCsv());

            $numero = 1;
            foreach ($solicitudes as $solicitud) {
                fputcsv($handle, MatrizNovedades::filaCsv($solicitud, $numero));
                $numero++;
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    private function exportXlsx(Collection $solicitudes, ?CarbonInterface $desde, ?CarbonInterface $hasta): BinaryFileResponse
    {
        $path = $this->matriz->generar($solicitudes, $desde, $hasta);

        return response()->download($path, $this->baseFilename().'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function baseFilename(): string
    {
        return 'PHM-08-F-002-matriz-novedades-'.now()->format('Y-m-d-His');
    }
}
