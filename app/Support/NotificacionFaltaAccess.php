<?php

namespace App\Support;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\User;

class NotificacionFaltaAccess
{
    public static function puedeGestionar(User $user): bool
    {
        if ($user->rol === AppRole::Superusuario || $user->rol === AppRole::Decano) {
            return true;
        }

        return $user->hasCapability(CapabilityType::EmitirNotificacionesFaltas);
    }
}
