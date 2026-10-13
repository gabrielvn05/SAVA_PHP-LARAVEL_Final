<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OficioLimpiarCacheCommand extends Command
{
    protected $signature = 'oficio:limpiar-cache';

    protected $description = 'Elimina PDFs cacheados de oficios para forzar regeneración';

    public function handle(): int
    {
        $dir = storage_path('app/oficios/pdf');
        if (! is_dir($dir)) {
            $this->info('No hay carpeta de caché.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach (glob($dir.DIRECTORY_SEPARATOR.'*.pdf') ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }

        $this->info("Se eliminaron {$count} archivo(s) PDF en caché.");

        return self::SUCCESS;
    }
}
