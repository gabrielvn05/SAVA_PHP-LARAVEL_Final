<?php

namespace App\Services;

use App\Enums\AccesoResultado;
use App\Models\AccesoEvento;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AccesoEventoService
{
    public function registrar(
        AccesoResultado $resultado,
        ?User $user = null,
        ?string $email = null,
        string $canal = 'clave',
        ?User $actor = null,
    ): void {
        AccesoEvento::query()->create([
            'user_id' => $user?->id,
            'actor_id' => $actor?->id ?? $user?->id,
            'email' => $email !== null && $email !== '' ? mb_strtolower($email) : $user?->email,
            'resultado' => $resultado,
            'canal' => $canal,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500) ?: null,
            'occurred_at' => now(),
        ]);
    }
}
