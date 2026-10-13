<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperusuarioSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERUSER_EMAIL', 'admin@sava.local');
        $password = env('SUPERUSER_PASSWORD');

        if (! $password) {
            $this->command?->warn('SUPERUSER_PASSWORD no está definido en .env; se omite la creación del superusuario.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => strtolower($email)],
            [
                'nombres' => 'Super',
                'apellidos' => 'Administrador',
                'rol' => AppRole::Superusuario,
                'activo' => true,
                'password' => $password,
                'email_verified_at' => now(),
            ]
        );

        $this->command?->info("Superusuario listo: {$email}");
    }
}
