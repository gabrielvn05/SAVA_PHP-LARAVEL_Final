<?php

namespace App\Support;

use App\Enums\PermisoMotivo;
use App\Enums\SolicitudTipo;
use Carbon\Carbon;

class SolicitudPermisoRules
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{fecha_inicio: string, fecha_fin: string, motivo: string, detalle_extra: array<string, string>}|array{error: string}
     */
    public static function resolve(array $validated): array
    {
        if (($validated['tipo'] ?? '') !== SolicitudTipo::Permiso->value) {
            return [
                'error' => 'Tipo de solicitud inválido.',
            ];
        }

        $fecha = $validated['fecha_permiso'] ?? null;
        if (! is_string($fecha) || $fecha === '') {
            return ['error' => 'Indique la fecha del permiso.'];
        }

        $horaInicio = $validated['hora_inicio_permiso'] ?? null;
        $horaFin = $validated['hora_fin_permiso'] ?? null;
        if (! is_string($horaInicio) || $horaInicio === '' || ! is_string($horaFin) || $horaFin === '') {
            return ['error' => 'Indique la hora de inicio y fin de la falta.'];
        }

        if (strcmp($horaFin, $horaInicio) <= 0) {
            return ['error' => 'La hora de fin debe ser posterior a la hora de inicio.'];
        }

        $motivoEnum = PermisoMotivo::tryFrom((string) ($validated['motivo_permiso'] ?? ''));
        if ($motivoEnum === null) {
            return ['error' => 'Seleccione un motivo válido para el permiso.'];
        }

        $motivoTexto = $motivoEnum->label();
        $motivoOtro = trim((string) ($validated['motivo_permiso_otro'] ?? ''));
        if ($motivoEnum === PermisoMotivo::Otro) {
            if ($motivoOtro === '') {
                return ['error' => 'Especifique el motivo cuando elige «Otra opción».'];
            }
            $motivoTexto .= ': '.$motivoOtro;
        }

        return [
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'motivo' => $motivoTexto,
            'detalle_extra' => array_filter([
                'fecha_permiso' => $fecha,
                'hora_inicio_permiso' => $horaInicio,
                'hora_fin_permiso' => $horaFin,
                'motivo_permiso' => $motivoEnum->value,
                'motivo_permiso_otro' => $motivoOtro !== '' ? $motivoOtro : null,
            ], static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    public static function formatHora(string $hora): string
    {
        try {
            return Carbon::createFromFormat('H:i', substr($hora, 0, 5))->format('H:i');
        } catch (\Throwable) {
            return $hora;
        }
    }
}
