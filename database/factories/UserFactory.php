<?php

namespace Database\Factories;

use App\Enums\AppRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'nombres' => fake()->firstName(),
            'apellidos' => fake()->lastName(),
            'rol' => AppRole::Administrativo,
            'activo' => true,
            'cedula' => fake()->numerify('##########'),
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
}
