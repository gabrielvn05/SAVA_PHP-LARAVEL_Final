<?php

namespace App\Policies;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Models\AccountRequest;
use App\Models\User;

class AccountRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->rol, [AppRole::Decano, AppRole::Secretaria, AppRole::Superusuario], true);
    }

    public function update(User $user, AccountRequest $accountRequest): bool
    {
        if ($user->hasCapability(CapabilityType::GestionarUsuarios)) {
            return true;
        }

        return $user->rol === AppRole::Secretaria;
    }
}
