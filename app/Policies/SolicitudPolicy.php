<?php

namespace App\Policies;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Models\User;

class SolicitudPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Solicitud $solicitud): bool
    {
        return $solicitud->creado_por === $user->id
            || $user->hasCapability(CapabilityType::RevisarSolicitudes)
            || $user->hasCapability(CapabilityType::AprobarSolicitudes);
    }

    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityType::GenerarSolicitudes);
    }

    public function update(User $user, Solicitud $solicitud): bool
    {
        if (in_array($solicitud->estado, [
            SolicitudEstado::Aprobada,
            SolicitudEstado::Rechazada,
            SolicitudEstado::PendienteAprobacionDecano,
        ], true)) {
            return false;
        }

        if ($solicitud->creado_por === $user->id) {
            return in_array($solicitud->estado, [
                SolicitudEstado::EnBorrador,
                SolicitudEstado::EnRevisionSecretaria,
            ], true);
        }

        return $solicitud->estado === SolicitudEstado::EnRevisionSecretaria
            && ($user->hasCapability(CapabilityType::RevisarSolicitudes)
                || $user->hasCapability(CapabilityType::AprobarSolicitudes));
    }

    public function delete(User $user, Solicitud $solicitud): bool
    {
        if ($solicitud->creado_por !== $user->id) {
            return false;
        }

        if ($solicitud->estado === SolicitudEstado::EnBorrador) {
            return true;
        }

        return $solicitud->estado === SolicitudEstado::EnRevisionSecretaria
            && $solicitud->revisado_por === null;
    }

    public function revisar(User $user, Solicitud $solicitud): bool
    {
        return $user->hasCapability(CapabilityType::RevisarSolicitudes)
            && $solicitud->estado === SolicitudEstado::EnRevisionSecretaria;
    }

    public function aprobar(User $user, Solicitud $solicitud): bool
    {
        return $user->hasCapability(CapabilityType::AprobarSolicitudes)
            && $solicitud->estado === SolicitudEstado::PendienteAprobacionDecano;
    }
}
