<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;

class SolicitudWorkflowService
{
    public function estadoTrasRevisionSecretaria(AppRole $creadorRol, bool $aprobado): SolicitudEstado
    {
        if (! $aprobado) {
            return SolicitudEstado::Rechazada;
        }

        if ($creadorRol === AppRole::Decano) {
            return SolicitudEstado::Aprobada;
        }

        return SolicitudEstado::PendienteAprobacionDecano;
    }

    public function solicitudCreadaPorDecano(AppRole $creadorRol): bool
    {
        return $creadorRol === AppRole::Decano;
    }

    public function estadoInicial(AppRole $creadorRol): SolicitudEstado
    {
        if ($this->solicitudCreadaPorDecano($creadorRol)) {
            return SolicitudEstado::Aprobada;
        }

        return SolicitudEstado::EnRevisionSecretaria;
    }
}
