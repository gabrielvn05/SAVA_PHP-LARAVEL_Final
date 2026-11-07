<?php

namespace App\Support;

class AuditLogPresenter
{
    /** @var list<string> */
    private const SENSIBLES = [
        'password',
        'remember_token',
        'session_token',
        'password_confirmation',
        'current_password',
        'new_password',
    ];

    /** @var list<string> */
    private const OMITIR_EN_RESUMEN = [
        'id',
        'created_at',
        'updated_at',
        'password',
        'remember_token',
        'session_token',
    ];

    public static function accionLabel(string $action): string
    {
        return match (strtoupper($action)) {
            'INSERT' => 'Creación',
            'UPDATE' => 'Actualización',
            'DELETE' => 'Eliminación',
            default => $action,
        };
    }

    public static function accionBadge(string $action): string
    {
        return match (strtoupper($action)) {
            'INSERT' => 'badge--success',
            'UPDATE' => 'badge--info',
            'DELETE' => 'badge--danger',
            default => 'badge--muted',
        };
    }

    public static function tablaLabel(string $table): string
    {
        return match ($table) {
            'users' => 'Usuarios',
            'solicitudes' => 'Solicitudes',
            'biblioteca_documentos' => 'Biblioteca',
            'account_requests' => 'Solicitudes de cuenta',
            'notificacion_faltas' => 'Notificaciones TH',
            'firma_ec_envios' => 'FirmaEC',
            'user_capabilities' => 'Capacidades',
            default => ucfirst(str_replace('_', ' ', $table)),
        };
    }

    public static function campoLabel(string $campo): string
    {
        return match ($campo) {
            'email' => 'correo',
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'rol' => 'rol',
            'activo' => 'estado',
            'cedula' => 'cédula',
            'celular' => 'celular',
            'carrera' => 'carrera',
            'estado' => 'estado',
            'tipo' => 'tipo',
            'motivo' => 'motivo',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'detalle' => 'detalle',
            'titulo' => 'título',
            'nombre' => 'nombre',
            default => str_replace('_', ' ', $campo),
        };
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>|null
     */
    public static function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array($key, self::SENSIBLES, true)) {
                $out[$key] = $value === null ? null : '••••';

                continue;
            }

            $out[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function resumen(?array $old, ?array $new, string $action): string
    {
        $action = strtoupper($action);
        $old = self::redact($old) ?? [];
        $new = self::redact($new) ?? [];

        if ($action === 'INSERT' || ($old === [] && $new !== [])) {
            $pista = self::pista($new);

            return $pista !== null ? "Registro creado: {$pista}" : 'Registro creado';
        }

        if ($action === 'DELETE') {
            $pista = self::pista($old);

            return $pista !== null ? "Registro eliminado: {$pista}" : 'Registro eliminado';
        }

        $cambiados = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $key) {
            if (in_array($key, self::OMITIR_EN_RESUMEN, true)) {
                continue;
            }
            if (($old[$key] ?? null) != ($new[$key] ?? null)) {
                $cambiados[] = self::campoLabel((string) $key);
            }
        }

        if ($cambiados === []) {
            return 'Sin cambios visibles';
        }

        $mostrados = array_slice($cambiados, 0, 4);
        $extra = count($cambiados) - count($mostrados);
        $texto = implode(', ', $mostrados);

        return $extra > 0 ? "{$texto} y {$extra} más" : $texto;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function pista(array $data): ?string
    {
        foreach (['email', 'motivo', 'titulo', 'nombre', 'codigo_oficio', 'cedula'] as $key) {
            $valor = $data[$key] ?? null;
            if (is_scalar($valor) && trim((string) $valor) !== '') {
                $texto = mb_strimwidth(trim((string) $valor), 0, 80, '…');

                return self::campoLabel($key).' '.$texto;
            }
        }

        $nombre = trim((string) (($data['nombres'] ?? '').' '.($data['apellidos'] ?? '')));

        return $nombre !== '' ? $nombre : null;
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    public static function json(?array $data): string
    {
        $redacted = self::redact($data);
        if ($redacted === null || $redacted === []) {
            return '—';
        }

        return json_encode(
            $redacted,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '—';
    }
}
