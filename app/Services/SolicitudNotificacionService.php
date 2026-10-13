<?php

namespace App\Services;

use App\Enums\SolicitudEstado;
use App\Mail\SolicitudResultadoMail;
use App\Models\Solicitud;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SolicitudNotificacionService
{
    public function enviarResultadoFinal(Solicitud $solicitud): bool
    {
        $solicitud->loadMissing('creador');

        if (! in_array($solicitud->estado, [SolicitudEstado::Aprobada, SolicitudEstado::Rechazada], true)) {
            return false;
        }

        $email = $solicitud->creador?->email;
        if (! is_string($email) || $email === '') {
            return false;
        }

        try {
            Mail::to($email)->send(new SolicitudResultadoMail($solicitud));

            return true;
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de resultado de solicitud', [
                'solicitud_id' => $solicitud->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            report($e);

            return false;
        }
    }
}
