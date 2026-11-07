<?php

namespace App\Enums;

enum AccesoResultado: string
{
    case Exito = 'exito';
    case ClaveIncorrecta = 'clave_incorrecta';
    case CuentaInactiva = 'cuenta_inactiva';
    case MicrosoftFallido = 'microsoft_fallido';
    case SesionCerrada = 'sesion_cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Exito => 'Ingreso correcto',
            self::ClaveIncorrecta => 'Clave incorrecta',
            self::CuentaInactiva => 'Cuenta inactiva',
            self::MicrosoftFallido => 'Microsoft rechazado',
            self::SesionCerrada => 'Sesión cerrada',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Exito => 'badge--success',
            self::ClaveIncorrecta, self::MicrosoftFallido => 'badge--danger',
            self::CuentaInactiva, self::SesionCerrada => 'badge--warning',
        };
    }
}
