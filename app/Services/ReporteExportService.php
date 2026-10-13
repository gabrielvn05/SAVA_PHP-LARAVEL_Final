<?php

namespace App\Services;

use App\Models\Solicitud;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteExportService
{
    public function exportGeneral(Collection $solicitudes): StreamedResponse
    {
        $filename = 'reporte-solicitudes-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($solicitudes): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID', 'Solicitante', 'Correo', 'Rol', 'Tipo', 'Estado',
                'Fecha inicio', 'Fecha fin', 'Motivo', 'Código trámite', 'Creada',
            ]);

            foreach ($solicitudes as $solicitud) {
                $detalle = $solicitud->detalle ?? [];
                fputcsv($handle, [
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
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
