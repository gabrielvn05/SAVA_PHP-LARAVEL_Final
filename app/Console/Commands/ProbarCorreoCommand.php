<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ProbarCorreoCommand extends Command
{
    protected $signature = 'sava:probar-correo {destino? : Correo destino (por defecto MAIL_FROM_ADDRESS)}';

    protected $description = 'Envía un correo de prueba con la configuración MAIL_* actual';

    public function handle(): int
    {
        $destino = $this->argument('destino') ?: config('mail.from.address');
        if (! is_string($destino) || trim($destino) === '') {
            $this->error('Indique un destino o configure MAIL_FROM_ADDRESS.');

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $this->line('Mailer: '.$mailer);
        $this->line('Host: '.config('mail.mailers.smtp.host'));
        $this->line('From: '.config('mail.from.address'));

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn('Este mailer no entrega al buzón. Cambie MAIL_MAILER=smtp en .env');

            return self::FAILURE;
        }

        try {
            Mail::mailer($mailer)->raw(
                'Correo de prueba SAVA — '.now()->toIso8601String(),
                fn ($message) => $message->to($destino)->subject('Prueba SMTP SAVA'),
            );
            $this->info("Enviado a {$destino}. Revise bandeja y spam.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
