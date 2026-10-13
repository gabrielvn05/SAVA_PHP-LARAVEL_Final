<?php

namespace App\Enums;

enum SolicitudEstado: string
{
    case EnBorrador = 'en_borrador';
    case EnRevisionSecretaria = 'en_revision_secretaria';
    case PendienteAprobacionDecano = 'pendiente_aprobacion_decano';
    case Aprobada = 'aprobada';
    case Rechazada = 'rechazada';

    public function label(): string
    {
        return match ($this) {
            self::EnBorrador => 'En borrador',
            self::EnRevisionSecretaria => 'En revisión (Secretaría)',
            self::PendienteAprobacionDecano => 'Pendiente aprobación Decano',
            self::Aprobada => 'Aprobada',
            self::Rechazada => 'Rechazada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::EnBorrador => 'badge--muted',
            self::EnRevisionSecretaria => 'badge--warning',
            self::PendienteAprobacionDecano => 'badge--info',
            self::Aprobada => 'badge--success',
            self::Rechazada => 'badge--danger',
        };
    }
}
