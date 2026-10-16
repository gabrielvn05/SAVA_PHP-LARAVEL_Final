<?php

namespace App\Support;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\User;
use App\Models\UserCapability;

class DelegacionDecano
{
    /** @return list<CapabilityType> */
    public static function capacidadesQuePuedeOtorgar(User $actor): array
    {
        if ($actor->rol === AppRole::Superusuario) {
            return CapabilityType::cases();
        }

        if ($actor->rol !== AppRole::Decano) {
            return [];
        }

        return [
            CapabilityType::AprobarSolicitudes,
            CapabilityType::EmitirNotificacionesFaltas,
            CapabilityType::RevisarSolicitudes,
            CapabilityType::GenerarSolicitudes,
        ];
    }

    public static function esDelegada(User $actor, CapabilityType $capability): bool
    {
        if (in_array($actor->rol, [AppRole::Decano, AppRole::Superusuario], true)) {
            return false;
        }

        if (in_array($capability, $actor->rol->defaultCapabilities(), true)) {
            return false;
        }

        return $actor->capabilities()
            ->where('capability', $capability->value)
            ->exists();
    }

    public static function decanoTitular(User $actor, CapabilityType $capability): ?User
    {
        if ($actor->rol === AppRole::Decano && $actor->activo) {
            return $actor;
        }

        $otorgadoPor = UserCapability::query()
            ->where('user_id', $actor->id)
            ->where('capability', $capability->value)
            ->value('otorgado_por');

        if ($otorgadoPor) {
            $otorgante = User::query()->find($otorgadoPor);
            if ($otorgante && $otorgante->activo && $otorgante->rol === AppRole::Decano) {
                return $otorgante;
            }
        }

        return User::query()
            ->where('rol', AppRole::Decano)
            ->where('activo', true)
            ->orderBy('created_at')
            ->first();
    }
}
