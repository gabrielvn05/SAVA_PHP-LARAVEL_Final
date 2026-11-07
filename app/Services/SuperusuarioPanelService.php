<?php

namespace App\Services;

use App\Enums\AccountRequestStatus;
use App\Models\AccountRequest;
use App\Models\User;
use App\Models\UserCapability;
use App\Support\SingleSessionEnforcer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SuperusuarioPanelService
{
    /** @return array{inactivas: int, claves: int, altas: int, delegaciones: int, sesiones: int} */
    public function contadores(): array
    {
        return [
            'inactivas' => User::query()->where('activo', false)->count(),
            'claves' => User::query()->where('force_password_change', true)->count(),
            'altas' => AccountRequest::query()->where('status', AccountRequestStatus::Pendiente)->count(),
            'delegaciones' => $this->delegaciones()->count(),
            'sesiones' => $this->sesionesAbiertas()->count(),
        ];
    }

    /** @return Collection<int, User> */
    public function cuentasInactivas(): Collection
    {
        return User::query()
            ->where('activo', false)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, User> */
    public function clavesPendientes(): Collection
    {
        return User::query()
            ->where('force_password_change', true)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, AccountRequest> */
    public function altasPendientes(): Collection
    {
        return AccountRequest::query()
            ->where('status', AccountRequestStatus::Pendiente)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    /** @return Collection<int, UserCapability> */
    public function delegaciones(): Collection
    {
        return UserCapability::query()
            ->with(['user', 'otorgadoPor'])
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (UserCapability $capacidad): bool {
                $usuario = $capacidad->user;
                $capability = $capacidad->capability;

                if ($usuario === null || $capability === null || $usuario->rol === \App\Enums\AppRole::Superusuario) {
                    return false;
                }

                return ! in_array($capability, $usuario->rol->defaultCapabilities(), true);
            })
            ->values();
    }

    public function usaSesionesEnBase(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }

    /**
     * @return Collection<int, object{user: User, last_activity: \Illuminate\Support\Carbon, ip_address: ?string}>
     */
    public function sesionesAbiertas(): Collection
    {
        if (! $this->usaSesionesEnBase()) {
            return collect();
        }

        $limite = now()->subMinutes((int) config('session.lifetime'))->getTimestamp();
        $filas = DB::table('sessions')
            ->select('user_id', DB::raw('max(last_activity) as last_activity'), DB::raw('max(ip_address) as ip_address'))
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $limite)
            ->groupBy('user_id')
            ->orderByDesc('last_activity')
            ->get();

        $usuarios = User::query()
            ->whereIn('id', $filas->pluck('user_id'))
            ->get()
            ->keyBy('id');

        return $filas
            ->map(function (object $fila) use ($usuarios): ?object {
                $usuario = $usuarios->get($fila->user_id);
                if (! $usuario) {
                    return null;
                }

                return (object) [
                    'user' => $usuario,
                    'last_activity' => \Illuminate\Support\Carbon::createFromTimestamp((int) $fila->last_activity),
                    'ip_address' => $fila->ip_address,
                ];
            })
            ->filter()
            ->values();
    }

    public function cerrarSesion(User $usuario): void
    {
        $usuario->forceFill(['session_token' => Str::random(40)])->save();
        SingleSessionEnforcer::closeOtherSessions($usuario);
    }
}
