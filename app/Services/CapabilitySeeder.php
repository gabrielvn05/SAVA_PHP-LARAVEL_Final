<?php

namespace App\Services;

use App\Models\User;

class CapabilitySeeder
{
    public function seedForUser(User $user): void
    {
        $user->capabilities()->delete();

        $rows = collect($user->rol->defaultCapabilities())
            ->map(fn ($capability) => [
                'user_id' => $user->id,
                'capability' => $capability->value,
                'created_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            $user->capabilities()->insert($rows);
        }
    }
}
