<?php

namespace App\Policies;

use App\Models\NotificacionFalta;
use App\Models\User;
use App\Support\NotificacionFaltaAccess;

class NotificacionFaltaPolicy
{
    public function viewAny(User $user): bool
    {
        return NotificacionFaltaAccess::puedeGestionar($user);
    }

    public function view(User $user, NotificacionFalta $notificacionFalta): bool
    {
        return NotificacionFaltaAccess::puedeGestionar($user);
    }

    public function create(User $user): bool
    {
        return NotificacionFaltaAccess::puedeGestionar($user);
    }
}
