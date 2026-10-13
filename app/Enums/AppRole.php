<?php

namespace App\Enums;

enum AppRole: string
{
    case Superusuario = 'superusuario';
    case Decano = 'decano';
    case Secretaria = 'secretaria';
    case Administrativo = 'administrativo';
    case Docente = 'docente';
    case Mantenimiento = 'mantenimiento';

    public function label(): string
    {
        return match ($this) {
            self::Superusuario => 'Superusuario',
            self::Decano => 'Decano',
            self::Secretaria => 'Secretaría',
            self::Administrativo => 'Administrativo',
            self::Docente => 'Docente',
            self::Mantenimiento => 'Código de trabajo',
        };
    }

    /** @return list<CapabilityType> */
    public function defaultCapabilities(): array
    {
        return match ($this) {
            self::Superusuario => CapabilityType::cases(),
            self::Decano => [
                CapabilityType::RevisarSolicitudes,
                CapabilityType::AprobarSolicitudes,
                CapabilityType::GenerarSolicitudes,
                CapabilityType::GestionarUsuarios,
            ],
            self::Secretaria => [
                CapabilityType::RevisarSolicitudes,
                CapabilityType::GenerarSolicitudes,
            ],
            self::Administrativo, self::Docente, self::Mantenimiento => [
                CapabilityType::GenerarSolicitudes,
            ],
        };
    }
}
