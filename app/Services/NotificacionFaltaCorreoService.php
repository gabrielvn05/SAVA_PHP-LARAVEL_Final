<?php

namespace App\Services;

use App\Mail\NotificacionFaltaColaboradorMail;
use App\Models\NotificacionFalta;
use App\Models\User;
use App\Support\CorreoEnvioResultado;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificacionFaltaCorreoService
{
    public function enviarAvisoColaborador(NotificacionFalta $notificacion): CorreoEnvioResultado
    {
        $notificacion->loadMissing('destinatario');
        $destinatario = $this->resolverDestinatario($notificacion);

        if ($destinatario instanceof CorreoEnvioResultado) {
            return $destinatario;
        }

        $email = trim((string) $destinatario->email);

        $mailer = (string) config('mail.default');
        if (! app()->environment('testing') && in_array($mailer, ['log', 'array'], true)) {
            $hint = $mailer === 'log'
                ? 'MAIL_MAILER=log solo escribe en storage/logs/laravel.log; no llega al buzón.'
                : 'MAIL_MAILER=array no envía correos reales.';

            $this->registrarFallo($notificacion, $hint);

            return new CorreoEnvioResultado(
                false,
                "No se envió al buzón del colaborador ({$hint}) Configure SMTP en .env y ejecute php artisan config:clear.",
                $hint,
            );
        }

        if (trim((string) config('mail.from.address')) === '') {
            $this->registrarFallo($notificacion, 'MAIL_FROM_ADDRESS vacío.');

            return new CorreoEnvioResultado(
                false,
                'No se envió correo: falta MAIL_FROM_ADDRESS en la configuración del servidor.',
                'from_vacio',
            );
        }

        try {
            // Destinatario único: correo del colaborador notificado (definido en el Mailable).
            Mail::mailer($mailer)->send(new NotificacionFaltaColaboradorMail($notificacion));

            $notificacion->forceFill([
                'aviso_correo_enviado_at' => now(),
                'aviso_correo_destino' => $email,
                'aviso_correo_error' => null,
            ])->save();

            return new CorreoEnvioResultado(
                true,
                "Correo enviado al colaborador notificado ({$destinatario->nombreCompleto()}) en {$email}. Revise también spam.",
            );
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar aviso de notificación de falta', [
                'notificacion_id' => $notificacion->id,
                'destinatario_id' => $destinatario->id,
                'email_destino' => $email,
                'mailer' => $mailer,
                'error' => $e->getMessage(),
            ]);
            report($e);

            $this->registrarFallo($notificacion, $e->getMessage());

            return new CorreoEnvioResultado(
                false,
                "No se pudo enviar el correo al colaborador ({$email}). Revise MAIL_* o php artisan sava:probar-correo.",
                $e->getMessage(),
            );
        }
    }

    private function resolverDestinatario(NotificacionFalta $notificacion): User|CorreoEnvioResultado
    {
        $destinatario = $notificacion->destinatario;

        if ($destinatario === null || $destinatario->id !== $notificacion->destinatario_id) {
            $this->registrarFallo($notificacion, 'Destinatario inválido en la notificación.');

            return new CorreoEnvioResultado(
                false,
                'No se envió correo: no se encontró el colaborador asociado a esta notificación.',
                'destinatario_invalido',
            );
        }

        $email = trim((string) $destinatario->email);
        if ($email === '') {
            $this->registrarFallo($notificacion, 'El colaborador no tiene correo en su perfil.');

            return new CorreoEnvioResultado(
                false,
                'No se envió correo: el colaborador notificado no tiene correo en su perfil SAVA.',
                'email_vacio',
            );
        }

        return $destinatario;
    }

    private function registrarFallo(NotificacionFalta $notificacion, string $error): void
    {
        $notificacion->forceFill([
            'aviso_correo_error' => mb_substr($error, 0, 500),
        ])->save();
    }
}
