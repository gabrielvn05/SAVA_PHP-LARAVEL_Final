<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

class SolicitudValidator
{
    public static function fechaMinima(): Carbon
    {
        return now()->subDays(3)->startOfDay();
    }

    public static function validateFechaInicioDentroDelPlazo(string $fechaInicio): ?string
    {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $limite = self::fechaMinima();

        if ($inicio->lt($limite)) {
            return 'La fecha inicio no puede ser anterior a '.$limite->toDateString().' (máximo 3 días hacia atrás).';
        }

        return null;
    }

    public static function perfilInstitucionalCompleto(User $user): ?string
    {
        if (trim($user->cedula) === '') {
            return 'Completa tu cédula en el perfil antes de crear solicitudes.';
        }

        if (trim($user->carrera) === '') {
            return 'Completa tu carrera en el perfil antes de crear solicitudes.';
        }

        return null;
    }

    public static function anexoObligatorioParaTipo(string $tipo): bool
    {
        return in_array($tipo, ['enfermedad', 'calamidad_domestica'], true);
    }
}
