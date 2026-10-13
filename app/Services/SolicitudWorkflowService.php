<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;

class SolicitudWorkflowService
{
    public function estadoTrasRevisionSecretaria(
        AppRole $creadorRol,
        bool $aprobado,
        bool $revisorFirmaComoDecano = false,
    ): SolicitudEstado {
        if (! $aprobado) {
            return SolicitudEstado::Rechazada;
        }

        if ($creadorRol === AppRole::Decano || $revisorFirmaComoDecano) {
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

        if ($creadorRol === AppRole::Secretaria) {
            return SolicitudEstado::PendienteAprobacionDecano;
        }

        return SolicitudEstado::EnRevisionSecretaria;
    }
}
