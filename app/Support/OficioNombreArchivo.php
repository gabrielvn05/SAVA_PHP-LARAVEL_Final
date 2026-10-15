<?php

namespace App\Support;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;

class OficioNombreArchivo
{
    public static function base(Solicitud $solicitud): string
    {
        $codigo = (string) (($solicitud->detalle ?? [])['codigo_tramite'] ?? 'oficio');
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $codigo) ?: 'oficio';

        return $base;
    }

    public static function firmado(Solicitud $solicitud, string $extension): string
    {
        $base = self::base($solicitud);
        if ($solicitud->estado === SolicitudEstado::Aprobada) {
            return $base.'-firmado.'.$extension;
        }

        return $base.'.'.$extension;
    }
}
