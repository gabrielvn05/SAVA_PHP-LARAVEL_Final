<?php

namespace App\Support;

use App\Enums\FaltaMarcadoMotivo;
use App\Enums\SolicitudTipo;

class SolicitudFaltaMarcadoRules
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{fecha_inicio: string, fecha_fin: string, motivo: string, detalle_extra: array<string, string>}|array{error: string}
     */
    public static function resolve(array $validated): array
    {
        if (($validated['tipo'] ?? '') !== SolicitudTipo::FaltaMarcado->value) {
            return ['error' => 'Tipo de solicitud inválido.'];
        }

        $jornada = trim((string) ($validated['jornada'] ?? ''));
        if ($jornada === '') {
            return ['error' => 'Seleccione la jornada laboral.'];
        }

        $fecha = $validated['fecha_incidente'] ?? null;
        if (! is_string($fecha) || $fecha === '') {
            return ['error' => 'Indique la fecha del incidente.'];
        }

        $tipoMarcacion = (string) ($validated['tipo_marcacion_omitida'] ?? '');
        if (! in_array($tipoMarcacion, ['entrada', 'salida', 'entrada_salida'], true)) {
            return ['error' => 'Seleccione el tipo de marcación omitida o fallida.'];
        }

        $horaIngreso = trim((string) ($validated['hora_real_ingreso'] ?? ''));
        $horaSalida = trim((string) ($validated['hora_real_salida'] ?? ''));

        if ($tipoMarcacion === 'entrada' && $horaIngreso === '') {
            return ['error' => 'Indique la hora real de ingreso.'];
        }
        if ($tipoMarcacion === 'salida' && $horaSalida === '') {
            return ['error' => 'Indique la hora real de salida.'];
        }
        if ($tipoMarcacion === 'entrada_salida') {
            if ($horaIngreso === '' || $horaSalida === '') {
                return ['error' => 'Indique la hora real de ingreso y de salida.'];
            }
            if (strcmp($horaSalida, $horaIngreso) <= 0) {
                return ['error' => 'La hora real de salida debe ser posterior a la hora de ingreso.'];
            }
        }

        $motivoEnum = FaltaMarcadoMotivo::tryFrom((string) ($validated['motivo_falta_registro'] ?? ''));
        if ($motivoEnum === null) {
            return ['error' => 'Seleccione el motivo de la falta de registro.'];
        }

        return [
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'motivo' => $motivoEnum->label(),
            'detalle_extra' => array_filter([
                'jornada' => $jornada,
                'fecha_incidente' => $fecha,
                'tipo_marcacion_omitida' => $tipoMarcacion,
                'hora_real_ingreso' => $horaIngreso !== '' ? $horaIngreso : null,
                'hora_real_salida' => $horaSalida !== '' ? $horaSalida : null,
                'motivo_falta_registro' => $motivoEnum->value,
            ], static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    public static function formatHora(?string $hora): string
    {
        if ($hora === null || $hora === '') {
            return '—';
        }

        return SolicitudPermisoRules::formatHora($hora);
    }
}
