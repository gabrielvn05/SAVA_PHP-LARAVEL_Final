<?php

namespace App\Support;

use App\Enums\SolicitudTipo;
use Carbon\Carbon;

class SolicitudViajeRules
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{fecha_inicio: string, fecha_fin: string, motivo: string, detalle_extra: array<string, int|string>}|array{error: string}
     */
    public static function resolve(array $validated): array
    {
        if (($validated['tipo'] ?? '') !== SolicitudTipo::Viaje->value) {
            return ['error' => 'Tipo de solicitud inválido.'];
        }

        $fechaInicioFalta = $validated['fecha_inicio_viaje'] ?? null;
        $fechaFinFalta = $validated['fecha_fin_viaje'] ?? null;
        if (! is_string($fechaInicioFalta) || $fechaInicioFalta === ''
            || ! is_string($fechaFinFalta) || $fechaFinFalta === '') {
            return ['error' => 'Indique la fecha de inicio de la falta y la fecha de retorno.'];
        }

        if (strcmp($fechaFinFalta, $fechaInicioFalta) < 0) {
            return ['error' => 'La fecha de retorno no puede ser anterior al inicio de la falta.'];
        }

        $eventoDesde = self::composeFechaEvento($validated, 'evento_inicio');
        if (isset($eventoDesde['error'])) {
            return $eventoDesde;
        }
        $eventoHasta = self::composeFechaEvento($validated, 'evento_fin');
        if (isset($eventoHasta['error'])) {
            return $eventoHasta;
        }

        if (strcmp($eventoHasta['iso'], $eventoDesde['iso']) < 0) {
            return ['error' => 'La fecha de fin del evento no puede ser anterior a la de inicio.'];
        }

        $tipoViaje = trim((string) ($validated['tipo_viaje_evento'] ?? ''));
        if ($tipoViaje === '') {
            return ['error' => 'Seleccione el tipo de visita de campo.'];
        }

        $nombreEvento = trim((string) ($validated['nombre_evento'] ?? ''));
        if ($nombreEvento === '') {
            return ['error' => 'Indique el nombre del evento o estudio.'];
        }

        $lugarEvento = trim((string) ($validated['lugar_evento'] ?? ''));
        if ($lugarEvento === '') {
            return ['error' => 'Indique el lugar del evento (ciudad, país).'];
        }

        $rol = trim((string) ($validated['rol_especifico'] ?? ''));

        return [
            'fecha_inicio' => $fechaInicioFalta,
            'fecha_fin' => $fechaFinFalta,
            'motivo' => 'Visita de campo: '.$nombreEvento,
            'detalle_extra' => array_filter([
                'tipo_viaje_evento' => $tipoViaje,
                'nombre_evento' => $nombreEvento,
                'lugar_evento' => $lugarEvento,
                'rol_especifico' => $rol !== '' ? $rol : null,
                'evento_inicio_dia' => $eventoDesde['dia'],
                'evento_inicio_mes' => $eventoDesde['mes'],
                'evento_inicio_anio' => $eventoDesde['anio'],
                'evento_fin_dia' => $eventoHasta['dia'],
                'evento_fin_mes' => $eventoHasta['mes'],
                'evento_fin_anio' => $eventoHasta['anio'],
                'fecha_evento_desde' => $eventoDesde['iso'],
                'fecha_evento_hasta' => $eventoHasta['iso'],
            ], static fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{dia: int, mes: int, anio: int, iso: string}|array{error: string}
     */
    private static function composeFechaEvento(array $validated, string $prefix): array
    {
        $dia = (int) ($validated[$prefix.'_dia'] ?? 0);
        $mes = (int) ($validated[$prefix.'_mes'] ?? 0);
        $anio = (int) ($validated[$prefix.'_anio'] ?? 0);

        if ($dia < 1 || $dia > 31 || $mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            return ['error' => 'Complete día, mes y año válidos para las fechas del evento.'];
        }

        if (! checkdate($mes, $dia, $anio)) {
            return ['error' => 'Las fechas del evento no son válidas (revise día, mes y año).'];
        }

        $iso = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);

        return [
            'dia' => $dia,
            'mes' => $mes,
            'anio' => $anio,
            'iso' => $iso,
        ];
    }

    public static function mesLabel(int $mes): string
    {
        try {
            return Carbon::create(null, $mes, 1)->locale('es')->translatedFormat('F');
        } catch (\Throwable) {
            return (string) $mes;
        }
    }
}
