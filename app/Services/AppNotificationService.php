<?php

namespace App\Services;

use App\Enums\AppNotificationKind;
use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Enums\SolicitudEstado;
use App\Models\AccountRequest;
use App\Models\AppNotification;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Collection;

class AppNotificationService
{
    /** @return Collection<int, AppNotification> */
    public function recientesPara(User $user, int $limit = 20): Collection
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function contarNoLeidas(User $user): int
    {
        return AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function marcarTodasLeidas(User $user): void
    {
        AppNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function onSolicitudCreada(Solicitud $solicitud): void
    {
        if ($solicitud->estado === SolicitudEstado::EnBorrador) {
            return;
        }

        $solicitud->loadMissing('creador');
        $creador = $solicitud->creador;
        $nombre = $creador?->nombreCompleto() ?? 'Un usuario';
        $codigo = $solicitud->detalle['codigo_tramite'] ?? $solicitud->id;
        $url = route('solicitudes.show', $solicitud);

        $this->notifyCapacidad(
            CapabilityType::RevisarSolicitudes,
            AppNotificationKind::SolicitudNueva,
            'Nueva solicitud',
            "{$nombre} envió una solicitud de {$solicitud->tipo->label()} ({$codigo}).",
            $url,
            excludeUserId: $solicitud->creado_por,
        );
    }

    public function onSolicitudRevisadaSecretaria(Solicitud $solicitud, SolicitudEstado $nuevoEstado, bool $aprobado): void
    {
        $solicitud->loadMissing('creador');
        $codigo = $solicitud->detalle['codigo_tramite'] ?? $solicitud->id;
        $url = route('solicitudes.show', $solicitud);

        if ($aprobado && $nuevoEstado === SolicitudEstado::PendienteAprobacionDecano) {
            $this->notifyDecanos(
                AppNotificationKind::SolicitudPendienteDecano,
                'Solicitud pendiente de firma',
                "Secretaría aprobó la solicitud {$codigo}; requiere su firma.",
                route('solicitudes.show', $solicitud),
            );
        }

        $creador = $solicitud->creador;
        if ($creador === null || ! $this->debeNotificarCreador($creador)) {
            return;
        }

        if ($aprobado && $nuevoEstado === SolicitudEstado::PendienteAprobacionDecano) {
            $this->notifyUser(
                $creador,
                AppNotificationKind::SolicitudEstadoActualizado,
                'Solicitud en firma del Decano',
                "Su solicitud {$codigo} fue aprobada por Secretaría y está pendiente de firma del Decano.",
                $url,
            );
        } elseif (! $aprobado && $nuevoEstado === SolicitudEstado::Rechazada) {
            $this->notifyUser(
                $creador,
                AppNotificationKind::SolicitudResuelta,
                'Solicitud rechazada',
                "Su solicitud {$codigo} fue rechazada en revisión de Secretaría.",
                $url,
            );
        } elseif ($aprobado && $nuevoEstado === SolicitudEstado::Aprobada) {
            $this->notifyUser(
                $creador,
                AppNotificationKind::SolicitudResuelta,
                'Solicitud aprobada',
                "Su solicitud {$codigo} fue aprobada.",
                $url,
            );
        }
    }

    public function onSolicitudResueltaDecano(Solicitud $solicitud, bool $aprobado): void
    {
        $solicitud->loadMissing('creador');
        $creador = $solicitud->creador;
        if ($creador === null || ! $this->debeNotificarCreador($creador)) {
            return;
        }

        $codigo = $solicitud->detalle['codigo_tramite'] ?? $solicitud->id;
        $url = route('solicitudes.show', $solicitud);

        $this->notifyUser(
            $creador,
            AppNotificationKind::SolicitudResuelta,
            $aprobado ? 'Solicitud aprobada' : 'Solicitud rechazada',
            $aprobado
                ? "Su solicitud {$codigo} fue aprobada y firmada por el Decano."
                : "Su solicitud {$codigo} fue rechazada por el Decano.",
            $url,
        );
    }

    public function onSolicitudCuentaCreada(AccountRequest $accountRequest): void
    {
        $nombre = trim("{$accountRequest->nombres} {$accountRequest->apellidos}");
        $this->notifyDecanos(
            AppNotificationKind::SolicitudCuentaNueva,
            'Solicitud de cuenta',
            "Nueva solicitud de acceso de {$nombre} ({$accountRequest->email}).",
            route('admin.solicitudes-cuenta.index'),
        );
    }

    private function debeNotificarCreador(User $creador): bool
    {
        return in_array($creador->rol, [
            AppRole::Docente,
            AppRole::Administrativo,
            AppRole::Mantenimiento,
        ], true);
    }

    private function notifyDecanos(
        AppNotificationKind $kind,
        string $title,
        string $message,
        ?string $url = null,
    ): void {
        $decanos = User::query()
            ->where('rol', AppRole::Decano)
            ->where('activo', true)
            ->get();

        $this->notifyUsers($decanos, $kind, $title, $message, $url);
    }

    private function notifyCapacidad(
        CapabilityType $capability,
        AppNotificationKind $kind,
        string $title,
        string $message,
        ?string $url = null,
        ?string $excludeUserId = null,
    ): void {
        $users = User::query()
            ->where('activo', true)
            ->where('rol', '!=', AppRole::Superusuario)
            ->get()
            ->filter(fn (User $u): bool => $u->hasCapability($capability))
            ->when($excludeUserId, fn (Collection $c) => $c->reject(fn (User $u) => $u->id === $excludeUserId));

        $this->notifyUsers($users, $kind, $title, $message, $url);
    }

    /**
     * @param  iterable<User>  $users
     */
    private function notifyUsers(
        iterable $users,
        AppNotificationKind $kind,
        string $title,
        string $message,
        ?string $url = null,
    ): void {
        foreach ($users as $user) {
            $this->notifyUser($user, $kind, $title, $message, $url);
        }
    }

    private function notifyUser(
        User $user,
        AppNotificationKind $kind,
        string $title,
        string $message,
        ?string $url = null,
    ): void {
        AppNotification::query()->create([
            'user_id' => $user->id,
            'kind' => $kind->value,
            'title' => $title,
            'message' => $message,
            'action_url' => $url,
        ]);
    }
}
