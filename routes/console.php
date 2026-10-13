<?php

use App\Enums\SolicitudTipo;
use App\Services\OficioDatosService;
use App\Services\OficioPdfService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('oficio:limpiar-cache', function () {
    $dir = storage_path('app/oficios/pdf');
    if (! is_dir($dir)) {
        $this->info('No hay carpeta de caché.');

        return;
    }

    $count = 0;
    foreach (glob($dir.DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $file) {
        if (@unlink($file)) {
            $count++;
        }
    }

    $this->info("Se eliminaron {$count} archivo(s) PDF en caché.");
})->purpose('Elimina PDFs cacheados de oficios');

Artisan::command('oficio:diagnostico', function (OficioPdfService $pdf, OficioDatosService $datos) {
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
})->purpose('Estado de plantillas y caché de oficios');
