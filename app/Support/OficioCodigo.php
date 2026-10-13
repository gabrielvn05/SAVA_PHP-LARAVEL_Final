<?php

namespace App\Support;

use App\Enums\SolicitudTipo;
use App\Models\User;
use Carbon\CarbonInterface;

class OficioCodigo
{
    public const PREFIJO = 'FACIVITEC';

    public static function generar(User $solicitante, SolicitudTipo $tipo, ?CarbonInterface $fecha = null): string
    {
        $fecha ??= now();
        $ddmmaaaa = $fecha->format('dmY');
        $siglas = self::siglasTipo($tipo);
        $iniciales = self::inicialesSolicitante($solicitante);

        return sprintf('%s-%s-%s-%s', self::PREFIJO, $ddmmaaaa, $siglas, $iniciales);
    }

    public static function siglasTipo(SolicitudTipo $tipo): string
    {
        return match ($tipo) {
            SolicitudTipo::FaltaMarcado => 'REC',
            SolicitudTipo::CalamidadDomestica => 'CAL',
            SolicitudTipo::Enfermedad => 'MED',
            SolicitudTipo::Viaje => 'VIA',
            SolicitudTipo::Permiso => 'PER',
            SolicitudTipo::Justificacion => 'JUS',
        };
    }

    public static function inicialesSolicitante(User $user): string
    {
        $partes = preg_split('/\s+/u', trim($user->nombres.' '.$user->apellidos)) ?: [];
        $iniciales = '';
        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }
            $iniciales .= mb_strtoupper(mb_substr($parte, 0, 1));
        }

        if ($iniciales !== '') {
            return $iniciales;
        }

        $fallback = preg_replace('/[^A-Za-zÁÉÍÓÚÑáéíóúñ]/u', '', $user->email ?? '') ?? '';

        return strtoupper(mb_substr($fallback, 0, 3)) ?: 'XX';
    }

    public static function usaPlantillaInstitucional(SolicitudTipo $tipo): bool
    {
        return in_array($tipo, [
            SolicitudTipo::FaltaMarcado,
            SolicitudTipo::CalamidadDomestica,
            SolicitudTipo::Enfermedad,
            SolicitudTipo::Viaje,
            SolicitudTipo::Permiso,
            SolicitudTipo::Justificacion,
        ], true);
    }
}
