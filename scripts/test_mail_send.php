<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$to = $argv[1] ?? config('mail.from.address');

try {
    Illuminate\Support\Facades\Mail::raw('Prueba SMTP SAVA '.date('c'), function ($m) use ($to): void {
        $m->to($to)->subject('Prueba correo SAVA');
    });
    echo "Enviado a {$to}\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    exit(1);
}
