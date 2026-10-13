<?php

namespace App\Support;

use App\Models\Solicitud;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudAdjuntos
{
    /**
     * @return list<string>
     */
    public static function pathsAutorizados(Solicitud $solicitud): array
    {
        $paths = [];
        $detalle = $solicitud->detalle ?? [];

        foreach ($detalle['anexos'] ?? [] as $anexo) {
            if (! empty($anexo['path'])) {
                $paths[] = (string) $anexo['path'];
            }
        }

        if ($solicitud->justificativo_path) {
            $paths[] = $solicitud->justificativo_path;
        }

        return array_values(array_unique($paths));
    }

    public static function pathEsValido(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || Str::contains($path, '..')) {
            return false;
        }

        return (bool) preg_match('#^justificativos/[A-Za-z0-9._-]+$#', $path);
    }

    public static function perteneceASolicitud(Solicitud $solicitud, string $path): bool
    {
        if (! self::pathEsValido($path)) {
            return false;
        }

        return in_array($path, self::pathsAutorizados($solicitud), true);
    }

    public static function urlVista(Solicitud $solicitud, string $path): string
    {
        if (Route::has('solicitudes.adjunto')) {
            return route('solicitudes.adjunto', [
                'solicitud' => $solicitud,
                'f' => $path,
            ]);
        }

        return Storage::disk('public')->url($path);
    }
}
