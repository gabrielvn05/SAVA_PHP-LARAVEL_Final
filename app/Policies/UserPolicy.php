<?php

namespace App\Policies;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCapability(CapabilityType::GestionarUsuarios);
    }

    public function create(User $user): bool
    {
        return $user->rol === AppRole::Superusuario;
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasCapability(CapabilityType::GestionarUsuarios);
    }

    public function delegate(User $user, User $model): bool
    {
        if ($model->rol === AppRole::Superusuario || $model->id === $user->id) {
            return false;
        }

        return $user->rol === AppRole::Superusuario || $user->rol === AppRole::Decano;
    }

    public function resetPassword(User $user, User $model): bool
    {
        return $user->rol === AppRole::Superusuario;
    }

    public function importCsv(User $user): bool
    {
        return $user->rol === AppRole::Superusuario;
    }
}
