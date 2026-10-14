<?php

namespace App\Services;

use App\Models\User;
use App\Support\CedulaEcuador;
use App\Support\NotificacionFaltaMarcacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class NotificacionFaltaImportService
{
    public function __construct(private readonly NotificacionFaltaService $notificaciones) {}

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function importFromCsv(
        UploadedFile $file,
        User $emitidoPor,
        ?NotificacionFaltaCorreoService $correo = null,
    ): array {
        $contents = $file->get();
        if (! is_string($contents) || trim($contents) === '') {
            return ['created' => 0, 'skipped' => 0, 'emails_enviados' => 0, 'emails_fallidos' => 0, 'errors' => ['El archivo está vacío.']];
        }

        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $created = 0;
        $skipped = 0;
        $emailsEnviados = 0;
        $emailsFallidos = 0;
        $errors = [];
        $lineNumber = 0;

        foreach ($lines as $line) {
            $lineNumber++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = str_getcsv($line);
            if ($cols === [null] || $cols === false) {
                continue;
            }

            $cols = array_map(static fn ($v) => trim((string) $v), $cols);

            if ($lineNumber === 1 && $this->esEncabezado($cols)) {
                continue;
            }

            if (count($cols) < 3) {
                $errors[] = "Línea {$lineNumber}: se requieren cédula, fecha y tipo de marcación.";

                continue;
            }

            [$cedula, $fecha, $tipo] = [
                CedulaEcuador::normalizeDigits($cols[0]),
                $cols[1],
                Str::lower($cols[2]),
            ];
            $tipo = str_replace([' ', '-'], '_', $tipo);
            if ($tipo === 'entrada_y_salida') {
                $tipo = 'entrada_salida';
            }

            if (! in_array($tipo, NotificacionFaltaMarcacion::valores(), true)) {
                $errors[] = "Línea {$lineNumber}: tipo de marcación inválido ({$cols[2]}).";

                continue;
            }

            $destinatario = User::query()->where('cedula', $cedula)->first();
            if ($destinatario === null) {
                $errors[] = "Línea {$lineNumber}: no se encontró usuario con cédula {$cedula}.";

                continue;
            }

            try {
                $notificacion = $this->notificaciones->crear($emitidoPor, [
                    'destinatario_id' => $destinatario->id,
                    'fecha_falta' => $fecha,
                    'tipo_marcacion' => $tipo,
                ]);
                $created++;
                if ($correo !== null) {
                    $resultado = $correo->enviarAvisoColaborador($notificacion);
                    if ($resultado->enviadoAlBuzon) {
                        $emailsEnviados++;
                    } else {
                        $emailsFallidos++;
                    }
                }
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'notificacion_faltas_unica')) {
                    $skipped++;
                } else {
                    $errors[] = "Línea {$lineNumber}: no se pudo registrar ({$e->getMessage()}).";
                }
            } catch (\Throwable $e) {
                $errors[] = "Línea {$lineNumber}: {$e->getMessage()}";
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'emails_enviados' => $emailsEnviados,
            'emails_fallidos' => $emailsFallidos,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string>  $cols
     */
    private function esEncabezado(array $cols): bool
    {
        $first = Str::lower($cols[0] ?? '');

        return in_array($first, ['cedula', 'cédula', 'identificacion', 'identificación'], true);
    }
}
