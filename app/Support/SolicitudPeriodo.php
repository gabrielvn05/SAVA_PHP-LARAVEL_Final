<?php

namespace App\Support;

use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use Carbon\CarbonInterface;

class SolicitudPeriodo
{
    /** @var array<int, string> */
    private const MESES = [
        1 => 'ene',
        2 => 'feb',
        3 => 'mar',
        4 => 'abr',
        5 => 'may',
        6 => 'jun',
        7 => 'jul',
        8 => 'ago',
        9 => 'sep',
        10 => 'oct',
        11 => 'nov',
        12 => 'dic',
    ];

    public static function label(Solicitud $solicitud): string
    {
        $inicio = $solicitud->fecha_inicio;
        $fin = $solicitud->fecha_fin;
        if (! $inicio instanceof CarbonInterface || ! $fin instanceof CarbonInterface) {
            return '—';
        }

        $texto = self::rango($inicio, $fin);

        if ($solicitud->tipo !== SolicitudTipo::Permiso || ! $inicio->isSameDay($fin)) {
            return $texto;
        }

        $detalle = $solicitud->detalle ?? [];
        $horaInicio = $detalle['hora_inicio_permiso'] ?? null;
        $horaFin = $detalle['hora_fin_permiso'] ?? null;
        if (! is_string($horaInicio) || $horaInicio === '' || ! is_string($horaFin) || $horaFin === '') {
            return $texto;
        }

        return $texto.' · '.SolicitudPermisoRules::formatHora($horaInicio).'–'.SolicitudPermisoRules::formatHora($horaFin);
    }

    private static function rango(CarbonInterface $inicio, CarbonInterface $fin): string
    {
        if ($fin->lessThan($inicio)) {
            [$inicio, $fin] = [$fin, $inicio];
        }

        if ($inicio->isSameDay($fin)) {
            return self::dia($inicio);
        }

        if ($inicio->year === $fin->year && $inicio->month === $fin->month) {
            return $inicio->day.'–'.$fin->day.' '.self::MESES[$inicio->month].' '.$inicio->year;
        }

        if ($inicio->year === $fin->year) {
            return $inicio->day.' '.self::MESES[$inicio->month].' – '.$fin->day.' '.self::MESES[$fin->month].' '.$inicio->year;
        }

        return self::dia($inicio).' – '.self::dia($fin);
    }

    private static function dia(CarbonInterface $fecha): string
    {
        return $fecha->day.' '.self::MESES[$fecha->month].' '.$fecha->year;
    }
}
