<?php

namespace App\Enums;

enum AppNotificationKind: string
{
    case SolicitudNueva = 'solicitud_nueva';
    case SolicitudPendienteDecano = 'solicitud_pendiente_decano';
    case SolicitudEstadoActualizado = 'solicitud_estado';
    case SolicitudResuelta = 'solicitud_resuelta';
    case SolicitudCuentaNueva = 'solicitud_cuenta_nueva';
}
