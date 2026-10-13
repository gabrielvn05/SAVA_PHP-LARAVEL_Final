<?php

namespace App\Services;

use App\Enums\AccountRequestStatus;
use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\AccountRequest;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardStatsService
{
    public function forUser(User $user): array
    {
        $query = Solicitud::query();

        $esStaff = in_array($user->rol, [AppRole::Secretaria, AppRole::Decano], true);

        if (! $esStaff) {
            $query->where('creado_por', $user->id);
        }

        $total = (clone $query)->count();
        $aprobadas = (clone $query)->where('estado', SolicitudEstado::Aprobada)->count();
        $rechazadas = (clone $query)->where('estado', SolicitudEstado::Rechazada)->count();
        $enProceso = (clone $query)->whereIn('estado', [
            SolicitudEstado::EnBorrador,
            SolicitudEstado::EnRevisionSecretaria,
            SolicitudEstado::PendienteAprobacionDecano,
        ])->count();

        $recientes30Dias = (clone $query)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $finalizadas = $aprobadas + $rechazadas;
        $tasaAprobacion = $finalizadas > 0 ? round(($aprobadas / $finalizadas) * 1000) / 10 : 0;
        $tasaResolucion = $total > 0 ? round(($finalizadas / $total) * 1000) / 10 : 0;

        $porEstado = (clone $query)
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $porTipo = (clone $query)
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        $pendientesRevision = (int) ($porEstado[SolicitudEstado::EnRevisionSecretaria->value] ?? 0);
        $pendientesFirma = (int) ($porEstado[SolicitudEstado::PendienteAprobacionDecano->value] ?? 0);

        $estadoColors = [
            SolicitudEstado::EnBorrador->value => '#94a3b8',
            SolicitudEstado::EnRevisionSecretaria->value => '#b45309',
            SolicitudEstado::PendienteAprobacionDecano->value => '#1d6fb8',
            SolicitudEstado::Aprobada->value => '#0d7a4f',
            SolicitudEstado::Rechazada->value => '#b42318',
        ];

        $tipoColors = [
            'permiso' => '#0c3d7a',
            'justificacion' => '#1d6fb8',
            'viaje' => '#6366f1',
            'enfermedad' => '#b45309',
            'calamidad_domestica' => '#9333ea',
            'falta_marcado' => '#0d9488',
        ];

        $byEstado = $this->segments($porEstado, $total, $estadoColors, fn (string $key) => SolicitudEstado::from($key)->label());
        $byTipo = $this->segments($porTipo, $total, $tipoColors, fn (string $key) => SolicitudTipo::from($key)->label());

        $stats = [
            'total' => $total,
            'aprobadas' => $aprobadas,
            'rechazadas' => $rechazadas,
            'enProceso' => $enProceso,
            'pendientesRevision' => $pendientesRevision,
            'pendientesFirma' => $pendientesFirma,
            'recientes30Dias' => $recientes30Dias,
            'tasaAprobacion' => $tasaAprobacion,
            'tasaResolucion' => $tasaResolucion,
            'porEstado' => $porEstado,
            'porTipo' => $porTipo,
            'byEstado' => $byEstado,
            'byTipo' => $byTipo,
            'scopeLabel' => $esStaff ? 'Institución' : 'Mis trámites',
        ];

        if (in_array($user->rol, [AppRole::Decano, AppRole::Secretaria], true)) {
            $stats['solicitudesCuentaPendientes'] = AccountRequest::query()
                ->where('status', AccountRequestStatus::Pendiente)
                ->count();
        }

        return $stats;
    }

    /**
     * @param  Collection<string, int>  $counts
     * @param  array<string, string>  $colors
     * @return list<array{key: string, label: string, count: int, pct: float, color: string}>
     */
    private function segments(Collection $counts, int $total, array $colors, callable $label): array
    {
        $rows = [];

        foreach ($counts as $key => $count) {
            if ((int) $count <= 0) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'label' => $label((string) $key),
                'count' => (int) $count,
                'pct' => $total > 0 ? round(((int) $count / $total) * 1000) / 10 : 0,
                'color' => $colors[(string) $key] ?? '#64748b',
            ];
        }

        usort($rows, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        return $rows;
    }
}
