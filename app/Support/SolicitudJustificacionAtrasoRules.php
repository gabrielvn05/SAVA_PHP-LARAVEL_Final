<?php

namespace App\Support;

use App\Enums\JustificacionAtrasoMotivo;
use App\Enums\SolicitudTipo;

class SolicitudJustificacionAtrasoRules
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{fecha_inicio: string, fecha_fin: string, motivo: string, detalle_extra: array<string, string>}|array{error: string}
     */
    public static function resolve(array $validated): array
    {
        if (($validated['tipo'] ?? '') !== SolicitudTipo::Justificacion->value) {
            return ['error' => 'Tipo de solicitud inválido.'];
        }

        $hoy = now()->toDateString();
        $fecha = $validated['fecha_atraso'] ?? $hoy;
        if (! is_string($fecha) || $fecha === '') {
            $fecha = $hoy;
        }

        if ($fecha !== $hoy) {
            return ['error' => 'La justificación por atraso solo puede registrarse para la fecha de hoy.'];
        }

        $horaEstablecida = $validated['hora_llegada_establecida'] ?? null;
        $horaReal = $validated['hora_llegada_real'] ?? null;
        if (! is_string($horaEstablecida) || $horaEstablecida === '' || ! is_string($horaReal) || $horaReal === '') {
            return ['error' => 'Indique la hora establecida de llegada y la hora real de llegada.'];
        }

        if (strcmp($horaReal, $horaEstablecida) <= 0) {
            return ['error' => 'La hora de llegada real debe ser posterior a la hora establecida de llegada.'];
        }

        $motivoEnum = JustificacionAtrasoMotivo::tryFrom((string) ($validated['motivo_atraso'] ?? ''));
        if ($motivoEnum === null) {
            return ['error' => 'Seleccione un motivo válido para el atraso.'];
        }

        return [
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'motivo' => $motivoEnum->label(),
            'detalle_extra' => [
                'fecha_atraso' => $fecha,
                'hora_llegada_establecida' => $horaEstablecida,
                'hora_llegada_real' => $horaReal,
                'motivo_atraso' => $motivoEnum->value,
            ],
        ];
    }

    public static function formatHora(string $hora): string
    {
        return SolicitudPermisoRules::formatHora($hora);
    }
}
