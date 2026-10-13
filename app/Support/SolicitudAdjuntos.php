<?php

namespace App\Support;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    /**
     * @return list<array{path: string, nombre: string}>
     */
    public static function anexosActuales(Solicitud $solicitud): array
    {
        $detalle = $solicitud->detalle ?? [];
        $anexos = $detalle['anexos'] ?? [];

        if ($anexos === [] && $solicitud->justificativo_path) {
            return [[
                'path' => $solicitud->justificativo_path,
                'nombre' => $solicitud->justificativo_nombre ?: 'Justificativo',
            ]];
        }

        return array_values(array_filter($anexos, static fn (array $anexo): bool => filled($anexo['path'] ?? null)));
    }

    /**
     * @param  list<array{path: string, nombre: string}>  $anexos
     * @return list<array{path: string, nombre: string}>
     */
    public static function aplicarCambiosAdjuntos(Solicitud $solicitud, Request $request, array $anexos): array
    {
        $pathsAutorizados = self::pathsAutorizados($solicitud);

        foreach ($request->input('eliminar_anexos', []) as $path) {
            $path = str_replace('\\', '/', trim((string) $path));
            if (! in_array($path, $pathsAutorizados, true)) {
                continue;
            }

            $anexos = array_values(array_filter(
                $anexos,
                static fn (array $anexo): bool => ($anexo['path'] ?? '') !== $path,
            ));
            Storage::disk('public')->delete($path);
        }

        if ($request->hasFile('justificativo')) {
            $file = $request->file('justificativo');
            if ($file instanceof UploadedFile) {
                $reemplaza = $solicitud->justificativo_path;
                if ($reemplaza) {
                    $anexos = array_values(array_filter(
                        $anexos,
                        static fn (array $anexo): bool => ($anexo['path'] ?? '') !== $reemplaza,
                    ));
                    Storage::disk('public')->delete($reemplaza);
                }

                $anexos[] = [
                    'path' => $file->store('justificativos', 'public'),
                    'nombre' => $file->getClientOriginalName(),
                ];
            }
        }

        foreach ($request->file('anexos', []) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $anexos[] = [
                'path' => $file->store('justificativos', 'public'),
                'nombre' => $file->getClientOriginalName(),
            ];
        }

        return array_values($anexos);
    }

    /**
     * @param  list<array{path: string, nombre: string}>  $anexos
     * @return array{justificativo_path: ?string, justificativo_nombre: ?string, detalle_anexos: list<array{path: string, nombre: string}>}
     */
    public static function sincronizarCamposLegacy(array $anexos): array
    {
        $primero = $anexos[0] ?? null;

        return [
            'justificativo_path' => $primero['path'] ?? null,
            'justificativo_nombre' => $primero['nombre'] ?? null,
            'detalle_anexos' => $anexos,
        ];
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
