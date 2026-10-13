<?php

namespace App\Console\Commands;

use App\Enums\SolicitudTipo;
use App\Services\OficioDatosService;
use App\Services\OficioPdfService;
use Illuminate\Console\Command;

class OficioDiagnosticoCommand extends Command
{
    protected $signature = 'oficio:diagnostico';

    protected $description = 'Muestra estado de plantillas DOCX, LibreOffice y caché PDF';

    public function handle(OficioPdfService $pdf, OficioDatosService $datos): int
    {
        $this->info('Versión caché PDF: '.OficioPdfService::PLANTILLA_VERSION);
        $this->line('Vista previa PDF activa: '.(config('oficio.usar_pdf_en_visor') ? 'sí' : 'no (DOCX recomendado)'));
        $this->line('LibreOffice: '.((string) config('oficio.libreoffice_path')));
        $this->line('Puede convertir PDF: '.($pdf->puedeConvertir() ? 'sí' : 'no'));

        foreach ([
            SolicitudTipo::Viaje,
            SolicitudTipo::Enfermedad,
            SolicitudTipo::CalamidadDomestica,
            SolicitudTipo::FaltaMarcado,
            SolicitudTipo::Permiso,
            SolicitudTipo::Justificacion,
        ] as $tipo) {
            $path = $datos->plantillaPath($tipo);
            if ($path === null || ! is_file($path)) {
                $this->warn("Plantilla {$tipo->value}: no encontrada");

                continue;
            }
            $this->line(sprintf(
                'Plantilla %s: %s (%s, md5 %s)',
                $tipo->value,
                basename($path),
                date('Y-m-d H:i:s', filemtime($path)),
                md5_file($path) ?: '—',
            ));
        }

        $cacheDir = storage_path('app/oficios/pdf');
        $files = is_dir($cacheDir) ? glob($cacheDir.'/*.pdf') : [];
        $this->line('PDFs en caché: '.count($files ?: []));

        return self::SUCCESS;
    }
}
