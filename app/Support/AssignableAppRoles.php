<?php

namespace App\Support;

use App\Enums\AppRole;
use App\Models\User;

class AssignableAppRoles
{
    /** Roles que pueden elegirse en formularios (nunca Superusuario). */
    public static function forAssignment(): array
    {
        return array_values(array_filter(
            AppRole::cases(),
            static fn (AppRole $rol): bool => $rol !== AppRole::Superusuario,
        ));
    }

    /** Roles que un visitante puede pedir en el formulario público. */
    public static function forPublicRequest(): array
    {
        return [
            AppRole::Docente,
            AppRole::Administrativo,
            AppRole::Mantenimiento,
        ];
    }

    public static function canRequestPublicly(string $rol): bool
    {
        return in_array($rol, array_map(
            static fn (AppRole $caso): string => $caso->value,
            self::forPublicRequest(),
        ), true);
    }

    public static function canAssign(AppRole $rol): bool
    {
        return $rol !== AppRole::Superusuario;
    }

    public static function userVisibleInAdminList(User $target, User $actor): bool
    {
        if ($target->rol !== AppRole::Superusuario) {
            return true;
        }

        return $actor->rol === AppRole::Superusuario;
    }
}
