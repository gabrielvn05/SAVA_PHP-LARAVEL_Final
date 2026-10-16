<?php

namespace App\Enums;

enum CapabilityType: string
{
    case GestionarUsuarios = 'gestionar_usuarios';
    case RevisarSolicitudes = 'revisar_solicitudes';
    case AprobarSolicitudes = 'aprobar_solicitudes';
    case GenerarSolicitudes = 'generar_solicitudes';
    case EmitirNotificacionesFaltas = 'emitir_notificaciones_faltas';

    public function label(): string
    {
        return match ($this) {
            self::GestionarUsuarios => 'Gestionar usuarios',
            self::RevisarSolicitudes => 'Revisar solicitudes',
            self::AprobarSolicitudes => 'Firmar o dar visto bueno',
            self::GenerarSolicitudes => 'Generar solicitudes',
            self::EmitirNotificacionesFaltas => 'Emitir notificaciones de faltas (TH)',
        };
    }
}
