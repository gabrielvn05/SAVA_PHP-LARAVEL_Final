<?php

namespace Database\Factories;

use App\Enums\AppRole;
use App\Models\User;
use App\Support\CedulaEcuador;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /** @var array<string, true> */
    private static array $cedulasGeneradas = [];

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'rol' => AppRole::Administrativo,
            'activo' => true,
            'cedula' => self::uniqueValidCedula(),
            'celular' => fake()->numerify('09########'),
            'carrera' => 'software',
            'jornada' => '',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private static function uniqueValidCedula(): string
    {
        for ($i = 0; $i < 80; $i++) {
            $cedula = CedulaEcuador::fake();
            if (isset(self::$cedulasGeneradas[$cedula])) {
                continue;
            }
            if (User::query()->where('cedula', $cedula)->exists()) {
                continue;
            }
            self::$cedulasGeneradas[$cedula] = true;

            return $cedula;
        }

        throw new \RuntimeException('No se pudo generar una cédula de prueba única.');
    }
}
