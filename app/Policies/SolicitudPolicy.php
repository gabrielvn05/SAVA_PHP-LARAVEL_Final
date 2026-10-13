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
        if ($solicitud->creado_por === $user->id && $solicitud->estado === SolicitudEstado::EnBorrador) {
            return true;
        }

        if ($solicitud->creado_por === $user->id && $solicitud->estado === SolicitudEstado::EnRevisionSecretaria) {
            return true;
        }

        return $user->hasCapability(CapabilityType::RevisarSolicitudes)
            || $user->hasCapability(CapabilityType::AprobarSolicitudes);
    }

    public function delete(User $user, Solicitud $solicitud): bool
    {
        return $solicitud->creado_por === $user->id
            && $solicitud->estado === SolicitudEstado::EnBorrador;
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
