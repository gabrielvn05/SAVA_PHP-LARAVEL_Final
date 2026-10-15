<?php

namespace App\Support;

use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SolicitudAdjuntos
{
    public const DISK = 'local';

    public const DISK_LEGACY = 'public';

    /**
     * @return list<string>
     */
    public static function pathsAutorizados(Solicitud $solicitud): array
    {
        return array_values(array_map(
            static fn (array $anexo): string => (string) $anexo['path'],
            self::anexosActuales($solicitud),
        ));
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
    public static function filtrarAnexosConArchivo(array $anexos): array
    {
        return array_values(array_filter($anexos, static function (array $anexo): bool {
            $path = (string) ($anexo['path'] ?? '');

            return $path !== ''
                && self::pathEsValido($path)
                && self::diskConArchivo($path) !== null;
        }));
    }

    /**
     * @return list<array{path: string, nombre: string, url: string, kind: string}>
     */
    public static function documentosParaVista(Solicitud $solicitud): array
    {
        $documentos = [];

        foreach (self::filtrarAnexosConArchivo(self::anexosActuales($solicitud)) as $anexo) {
            $path = (string) $anexo['path'];
            $nombre = (string) ($anexo['nombre'] ?? 'Documento');

            $documentos[] = [
                'path' => $path,
                'nombre' => $nombre,
                'url' => self::urlVista($solicitud, $path),
                'kind' => AdjuntoPreview::kind($nombre),
            ];
        }

        return $documentos;
    }

    public static function restantesTrasEliminar(Solicitud $solicitud, Request $request): int
    {
        $autorizados = self::pathsAutorizados($solicitud);
        $eliminados = 0;

        foreach ($request->input('eliminar_anexos', []) as $path) {
            $path = str_replace('\\', '/', trim((string) $path));
            if (in_array($path, $autorizados, true)) {
                $eliminados++;
            }
        }

        return max(0, count(self::anexosActuales($solicitud)) - $eliminados);
    }

    public static function guardar(UploadedFile $file): string
    {
        return $file->store('justificativos', self::DISK);
    }

    public static function guardarContenido(string $binary, string $extension): string
    {
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin';
        $path = 'justificativos/'.Str::uuid().'.'.$extension;
        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }

    public static function diskConArchivo(string $path): ?string
    {
        foreach ([self::DISK, self::DISK_LEGACY] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return $disk;
            }
        }

        return null;
    }

    public static function borrar(string $path): void
    {
        foreach ([self::DISK, self::DISK_LEGACY] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->delete($path);
            }
        }
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
            self::borrar($path);
        }

        foreach ([$request->file('justificativo'), ...$request->file('anexos', [])] as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $anexos[] = [
                'path' => self::guardar($file),
                'nombre' => $file->getClientOriginalName(),
            ];
        }

        return self::filtrarAnexosConArchivo(array_values($anexos));
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
        return route('solicitudes.adjunto', [
            'solicitud' => $solicitud,
            'f' => $path,
        ]);
    }
}
