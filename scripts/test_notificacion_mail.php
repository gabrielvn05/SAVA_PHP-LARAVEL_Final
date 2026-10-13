<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$notificacion = App\Models\NotificacionFalta::query()
    ->with(['destinatario', 'decano'])
    ->latest()
    ->first();

if ($notificacion === null) {
    echo "No hay notificaciones en BD.\n";
    exit(1);
}

$to = $notificacion->destinatario?->email ?? '';
echo "Destinatario: {$to}\n";
echo "Mailer: ".config('mail.default')."\n";

try {
    Illuminate\Support\Facades\Mail::to($to)->send(new App\Mail\NotificacionFaltaColaboradorMail($notificacion));
    echo "Mailable enviado.\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    exit(1);
}
