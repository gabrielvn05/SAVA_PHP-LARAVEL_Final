<?php

namespace App\Services;

use App\Models\Solicitud;
use Illuminate\Support\Collection;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteExportService
{
    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    public function exportGeneral(Collection $solicitudes, string $format = 'xlsx'): StreamedResponse|BinaryFileResponse
    {
        return match ($format) {
            'csv' => $this->exportCsv($solicitudes),
            default => $this->exportXlsx($solicitudes),
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
            fputcsv($handle, $this->columnHeaders());

            foreach ($solicitudes as $solicitud) {
                fputcsv($handle, $this->solicitudRow($solicitud));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    private function exportXlsx(Collection $solicitudes): BinaryFileResponse
    {
        $filename = $this->baseFilename().'.xlsx';
        $tempBase = tempnam(sys_get_temp_dir(), 'reporte-xlsx-');
        if ($tempBase === false) {
            abort(500, 'No se pudo generar el archivo Excel.');
        }

        $path = $tempBase.'.xlsx';
        @unlink($tempBase);

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->columnHeaders()));

        foreach ($solicitudes as $solicitud) {
            $writer->addRow(Row::fromValues($this->solicitudRow($solicitud)));
        }

        $writer->close();

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function baseFilename(): string
    {
        return 'reporte-solicitudes-'.now()->format('Y-m-d-His');
    }

    /**
     * @return list<string>
     */
    private function columnHeaders(): array
    {
        return [
            'ID',
            'Solicitante',
            'Correo',
            'Rol',
            'Tipo',
            'Estado',
            'Fecha inicio',
            'Fecha fin',
            'Motivo',
            'Código trámite',
            'Creada',
        ];
    }

    /**
     * @return list<int|string>
     */
    private function solicitudRow(Solicitud $solicitud): array
    {
        $detalle = $solicitud->detalle ?? [];

        return [
            $solicitud->id,
            $solicitud->creador?->nombreCompleto() ?? '',
            $solicitud->creador?->email ?? '',
            $solicitud->creador?->rol?->label() ?? '',
            $solicitud->tipo->label(),
            $solicitud->estado->label(),
            $solicitud->fecha_inicio->format('Y-m-d'),
            $solicitud->fecha_fin->format('Y-m-d'),
            $solicitud->motivo,
            $detalle['codigo_tramite'] ?? '',
            $solicitud->created_at->format('Y-m-d H:i'),
        ];
    }
}
