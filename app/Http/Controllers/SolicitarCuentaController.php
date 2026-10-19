<?php

namespace App\Http\Controllers;

use App\Enums\AccountRequestStatus;
use App\Models\AccountRequest;
use App\Models\User;
use App\Services\AccountRequestValidator;
use App\Services\AppNotificationService;
use App\Support\AssignableAppRoles;
use App\Support\Carreras;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SolicitarCuentaController extends Controller
{
    public function __construct(
        private readonly AccountRequestValidator $validator,
        private readonly AppNotificationService $appNotifications,
    ) {}

    public function create(Request $request): View
    {
        return view('solicitar-cuenta.create', [
            'aviso' => $request->query('aviso'),
            'carreras' => Carreras::OPCIONES,
            'roles' => AssignableAppRoles::forPublicRequest(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $result = $this->validator->validate($request->all());

        if (! $result['ok']) {
            $redirect = redirect()->route('solicitar-cuenta.create', ['aviso' => $result['aviso']]);

            return $result['aviso'] === 'declaraciones_pendientes' ? $redirect->withInput() : $redirect;
        }

        $data = $result['data'];

        if (User::query()->where('email', $data['email'])->exists()) {
            return redirect()->route('solicitar-cuenta.create', ['aviso' => 'usuario_existe']);
        }

        if (User::query()->where('cedula', $data['cedula'])->where('cedula', '!=', '')->exists()) {
            return redirect()->route('solicitar-cuenta.create', ['aviso' => 'cedula_registrada']);
        }

        if (AccountRequest::query()
            ->where('email', $data['email'])
            ->where('status', AccountRequestStatus::Pendiente)
            ->exists()) {
            return redirect()->route('solicitar-cuenta.create', ['aviso' => 'solicitud_pendiente']);
        }

        if (AccountRequest::query()
            ->where('cedula', $data['cedula'])
            ->where('status', AccountRequestStatus::Pendiente)
            ->exists()) {
            return redirect()->route('solicitar-cuenta.create', ['aviso' => 'cedula_en_solicitud']);
        }

        try {
            $accountRequest = AccountRequest::create([
                ...$data,
                'status' => AccountRequestStatus::Pendiente,
            ]);
            $this->appNotifications->onSolicitudCuentaCreada($accountRequest);
        } catch (\Throwable) {
            return redirect()->route('solicitar-cuenta.create', ['aviso' => 'solicitud_pendiente']);
        }

        return redirect()->route('login', ['solicitud' => 'ok']);
    }
}
