<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountRequestStatus;
use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Mail\TemporaryPasswordMail;
use App\Models\AccountRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Support\TemporaryPasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AccountRequestController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): View
    {
        $this->authorize('viewAny', AccountRequest::class);

        $solicitudes = AccountRequest::query()
            ->orderByDesc('created_at')
            ->get();

        $pendientes = $solicitudes->where('status', AccountRequestStatus::Pendiente)->count();
        $user = auth()->user();
        $puedeAprobar = in_array($user->rol, [AppRole::Decano, AppRole::Superusuario], true);

        return view('admin.solicitudes-cuenta.index', compact('solicitudes', 'pendientes', 'puedeAprobar'));
    }

    public function aprobar(AccountRequest $accountRequest): RedirectResponse
    {
        $this->authorize('update', $accountRequest);

        if ($accountRequest->status !== AccountRequestStatus::Pendiente) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        if (! in_array(auth()->user()->rol, [AppRole::Decano, AppRole::Superusuario], true)) {
            abort(403, 'Solo el Decano puede aprobar solicitudes de cuenta.');
        }

        $tempPassword = TemporaryPasswordGenerator::generate();
        $email = strtolower($accountRequest->email);

        try {
            DB::transaction(function () use ($accountRequest, $tempPassword, $email): void {
                $existing = User::query()->where('email', $email)->first();

                if ($existing) {
                    $existing->update([
                        'password' => $tempPassword,
                        'force_password_change' => true,
                        'activo' => true,
                        'nombres' => $accountRequest->nombres,
                        'apellidos' => $accountRequest->apellidos,
                        'rol' => $accountRequest->rol_solicitado,
                        'cedula' => $accountRequest->cedula,
                        'celular' => $accountRequest->celular,
                        'carrera' => $accountRequest->carrera,
                        'jornada' => $accountRequest->jornada,
                    ]);
                    $user = $existing;
                } else {
                    $user = User::create([
                        'email' => $email,
                        'nombres' => $accountRequest->nombres,
                        'apellidos' => $accountRequest->apellidos,
                        'rol' => $accountRequest->rol_solicitado,
                        'activo' => true,
                        'cedula' => $accountRequest->cedula,
                        'celular' => $accountRequest->celular,
                        'carrera' => $accountRequest->carrera,
                        'jornada' => $accountRequest->jornada,
                        'password' => $tempPassword,
                        'force_password_change' => true,
                        'email_verified_at' => now(),
                    ]);
                    $this->audit->log('INSERT', $user);
                }

                Mail::to($email)->send(new TemporaryPasswordMail(
                    fullName: $user->nombreCompleto(),
                    email: $email,
                    temporaryPassword: $tempPassword,
                ));

                $accountRequest->update([
                    'status' => AccountRequestStatus::Aprobada,
                    'handled_by' => auth()->id(),
                    'handled_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo aprobar la solicitud ni enviar el correo. Verifica la configuración SMTP.');
        }

        return back()->with('success', "Cuenta aprobada. Se envió la clave temporal a {$email}.");
    }

    public function rechazar(Request $request, AccountRequest $accountRequest): RedirectResponse
    {
        $this->authorize('update', $accountRequest);

        if ($accountRequest->status !== AccountRequestStatus::Pendiente) {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $validated = $request->validate([
            'rechazo_comentario' => auth()->user()->rol === AppRole::Secretaria
                ? 'required|string|max:5000'
                : 'nullable|string|max:5000',
        ]);

        $accountRequest->update([
            'status' => AccountRequestStatus::Rechazada,
            'rechazo_comentario' => $validated['rechazo_comentario'] ?? null,
            'handled_by' => auth()->id(),
            'handled_at' => now(),
        ]);

        return back()->with('success', 'Solicitud rechazada.');
    }
}
