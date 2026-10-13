<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilUpdateRequest;
use App\Support\Carreras;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PerfilController extends Controller
{
    public function completar(): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->needsProfileCompletion()) {
            return redirect()->route('dashboard');
        }

        return view('perfil.completar', [
            'user' => $user,
            'carreras' => Carreras::OPCIONES,
        ]);
    }

    public function edit(): View
    {
        return view('perfil.edit', [
            'user' => auth()->user(),
            'carreras' => Carreras::OPCIONES,
        ]);
    }

    public function update(PerfilUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $eraIncompleto = $user->needsProfileCompletion();

        $user->update($request->validated());

        $destino = $eraIncompleto ? route('dashboard') : route('perfil.edit');

        return redirect($destino)
            ->with('success', $eraIncompleto
                ? 'Datos institucionales guardados. Ya puedes usar SAVA.'
                : 'Perfil actualizado correctamente.');
    }
}
