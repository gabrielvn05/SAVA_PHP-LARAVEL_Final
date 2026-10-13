<?php

namespace App\Support;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;

class SolicitudTimeline
{
    /**
     * @return list<array{id: string, label: string, fecha: ?string, status: string, icon: string}>
     */
    public static function for(Solicitud $solicitud): array
    {
        $estado = $solicitud->estado;
        $isRejected = $estado === SolicitudEstado::Rechazada;
        $rejectedAtSecretaria = $isRejected && $solicitud->revisado_por && ! $solicitud->firmado_por;
        $rejectedAtDecano = $isRejected && $solicitud->firmado_por;

        $secretariaStatus = match (true) {
            $estado === SolicitudEstado::EnBorrador => 'pending',
            $estado === SolicitudEstado::EnRevisionSecretaria => 'current',
            $rejectedAtSecretaria => 'rejected',
            default => 'completed',
        };

        $decanoStatus = match (true) {
            $rejectedAtSecretaria, $estado === SolicitudEstado::EnBorrador, $estado === SolicitudEstado::EnRevisionSecretaria => 'pending',
            $estado === SolicitudEstado::PendienteAprobacionDecano => 'current',
            $rejectedAtDecano => 'rejected',
            $estado === SolicitudEstado::Aprobada => 'completed',
            default => 'pending',
        };

        $finalStatus = match (true) {
            $estado === SolicitudEstado::Aprobada => 'completed',
            $isRejected => 'rejected',
            default => 'pending',
        };

        $finalLabel = match (true) {
            $estado === SolicitudEstado::Aprobada => 'Trámite aprobado',
            $isRejected => 'Trámite rechazado',
            default => 'Trámite finalizado',
        };

        $fmt = static fn ($date): ?string => $date?->format('Y-m-d H:i');

        return [
            [
                'id' => 'creada',
                'label' => 'Solicitar trámite',
                'fecha' => $fmt($solicitud->created_at),
                'status' => 'completed',
                'icon' => '📋',
            ],
            [
                'id' => 'revision_secretaria',
                'label' => 'Revisión (Secretaría)',
                'fecha' => in_array($secretariaStatus, ['completed', 'rejected', 'current'], true)
                    ? $fmt($solicitud->updated_at ?? $solicitud->created_at)
                    : null,
                'status' => $secretariaStatus,
                'icon' => '🔍',
            ],
            [
                'id' => 'revision_decano',
                'label' => 'Revisión (Decano)',
                'fecha' => in_array($decanoStatus, ['completed', 'rejected', 'current'], true)
                    ? $fmt($solicitud->fecha_firma ?? $solicitud->updated_at)
                    : null,
                'status' => $decanoStatus,
                'icon' => '✍',
            ],
            [
                'id' => 'final',
                'label' => $finalLabel,
                'fecha' => in_array($finalStatus, ['completed', 'rejected'], true)
                    ? $fmt($solicitud->fecha_firma ?? $solicitud->updated_at)
                    : null,
                'status' => $finalStatus,
                'icon' => $finalStatus === 'rejected' ? '✕' : ($finalStatus === 'completed' ? '✓' : '○'),
            ],
        ];
    }
}
