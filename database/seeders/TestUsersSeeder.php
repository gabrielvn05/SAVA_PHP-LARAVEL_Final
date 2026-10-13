<?php

namespace Database\Seeders;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUsersSeeder extends Seeder
{
    /** @return list<array{email: string, nombres: string, apellidos: string, rol: AppRole, cedula?: string, carrera?: string}> */
    private function users(): array
    {
        return [
            [
                'email' => 'decano@uleam.edu.ec',
                'nombres' => 'Decano',
                'apellidos' => 'Facultad',
                'rol' => AppRole::Decano,
                'cedula' => '1234567890',
                'carrera' => 'software',
            ],
            [
                'email' => 'secretaria@uleam.edu.ec',
                'nombres' => 'Secretaria',
                'apellidos' => 'Académica',
                'rol' => AppRole::Secretaria,
                'cedula' => '0987654321',
                'carrera' => 'software',
            ],
            [
                'email' => 'docente@uleam.edu.ec',
                'nombres' => 'Docente',
                'apellidos' => 'Ejemplo',
                'rol' => AppRole::Docente,
                'cedula' => '1122334455',
                'carrera' => 'software',
            ],
            [
                'email' => 'administrativo@uleam.edu.ec',
                'nombres' => 'Administrativo',
                'apellidos' => 'Ejemplo',
                'rol' => AppRole::Administrativo,
                'cedula' => '5566778899',
                'carrera' => 'software',
            ],
        ];
    }

    public function run(): void
    {
        $password = env('SEED_TEST_PASSWORD', 'SavaTest2026!');

        foreach ($this->users() as $data) {
            User::query()->updateOrCreate(
                ['email' => strtolower($data['email'])],
                [
                    'nombres' => $data['nombres'],
                    'apellidos' => $data['apellidos'],
                    'rol' => $data['rol'],
                    'activo' => true,
                    'cedula' => $data['cedula'] ?? '',
                    'celular' => $data['celular'] ?? '0990000000',
                    'carrera' => $data['carrera'] ?? 'software',
                    'jornada' => '',
                    'password' => $password,
                    'force_password_change' => false,
                    'email_verified_at' => now(),
                ]
            );
        }

        $this->command?->info('Usuarios de prueba listos (contraseña: SEED_TEST_PASSWORD del .env).');
    }
}
