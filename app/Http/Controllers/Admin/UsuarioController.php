<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Http\Controllers\Controller;
use App\Mail\TemporaryPasswordMail;
use App\Models\User;
use App\Models\UserCapability;
use App\Rules\StrongPassword;
use App\Services\AuditService;
use App\Services\UsuarioImportService;
use App\Support\AssignableAppRoles;
use App\Support\CedulaEcuador;
use App\Support\DelegacionDecano;
use App\Support\InstitutionalEmail;
use App\Support\TemporaryPasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsuarioController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly UsuarioImportService $importService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $actor = auth()->user();

        $usuarios = User::query()
            ->with('capabilities')
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get()
            ->filter(fn (User $usuario): bool => AssignableAppRoles::userVisibleInAdminList($usuario, $actor));

        $rolesAsignables = AssignableAppRoles::forAssignment();

        return view('admin.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => $rolesAsignables,
            'capabilities' => DelegacionDecano::capacidadesQuePuedeOtorgar($actor),
            'puedeCrear' => $actor->rol === AppRole::Superusuario,
            'puedeCambiarRol' => $actor->hasCapability(CapabilityType::GestionarUsuarios),
            'puedeDelegar' => $actor->rol === AppRole::Superusuario || $actor->rol === AppRole::Decano,
            'esSuperusuario' => $actor->rol === AppRole::Superusuario,
            'rutas' => [
                'importCsv' => Route::has('admin.usuarios.import-csv'),
                'rol' => Route::has('admin.usuarios.rol'),
                'estado' => Route::has('admin.usuarios.estado'),
                'resetClave' => Route::has('admin.usuarios.reset-clave'),
                'updateLegacy' => Route::has('admin.usuarios.update'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $roles = array_map(static fn (AppRole $rol): string => $rol->value, AssignableAppRoles::forAssignment());
        $cedulaRaw = trim((string) $request->input('cedula', ''));

        if (CedulaEcuador::containsLetters($cedulaRaw)) {
            return back()->withInput()->withErrors([
                'cedula' => 'La cédula solo debe contener números (sin letras).',
            ]);
        }

        $request->merge([
            'cedula' => CedulaEcuador::normalizeDigits($cedulaRaw),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email', function ($attribute, $value, $fail): void {
                if (! InstitutionalEmail::isAllowed((string) $value)) {
                    $fail(InstitutionalEmail::validationMessage());
                }
            }],
            'password' => ['required', 'string', new StrongPassword],
            'nombres' => 'required|string|max:120',
            'apellidos' => 'required|string|max:120',
            'cedula' => ['required', 'digits:10', Rule::unique('users', 'cedula')],
            'rol' => ['required', 'string', Rule::in($roles)],
        ], [
            'cedula.required' => 'Indica la cédula de identidad.',
            'cedula.digits' => 'La cédula ecuatoriana debe tener exactamente 10 dígitos.',
            'cedula.unique' => 'Esta cédula ya está registrada en una cuenta.',
        ]);

        $user = User::create([
            'email' => strtolower($validated['email']),
            'password' => $validated['password'],
            'nombres' => $validated['nombres'],
            'apellidos' => $validated['apellidos'],
            'cedula' => $validated['cedula'],
            'rol' => $validated['rol'],
            'activo' => true,
            'email_verified_at' => now(),
            'force_password_change' => true,
        ]);

        $this->audit->log('INSERT', $user);

        return back()->with('success', "Usuario {$user->email} creado. Debe cambiar la contraseña en el primer acceso.");
    }

    public function updateRol(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);

        if ($usuario->rol === AppRole::Superusuario) {
            return back()->with('error', 'El rol del superusuario no puede modificarse desde aquí.');
        }

        $roles = array_map(static fn (AppRole $rol): string => $rol->value, AssignableAppRoles::forAssignment());

        $validated = $request->validate([
            'rol' => ['required', 'string', Rule::in($roles)],
        ]);

        $nuevoRol = AppRole::from($validated['rol']);
        $old = $usuario->toArray();
        $usuario->update(['rol' => $nuevoRol]);
        $this->audit->log('UPDATE', $usuario, $old);

        return back()->with('success', 'Rol actualizado: '.$usuario->nombreCompleto().' ahora es '.$nuevoRol->label().'.');
    }

    public function updateEstado(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('update', $usuario);

        if ($usuario->rol === AppRole::Superusuario) {
            return back()->with('error', 'No puede desactivar la cuenta de superusuario.');
        }

        $validated = $request->validate([
            'activo' => ['required', Rule::in(['0', '1'])],
        ]);

        $old = $usuario->toArray();
        $usuario->update(['activo' => $validated['activo'] === '1']);
        $this->audit->log('UPDATE', $usuario, $old);

        $estado = $usuario->activo ? 'activo' : 'inactivo';

        return back()->with('success', "Estado actualizado: {$usuario->nombreCompleto()} quedó {$estado}.");
    }

    public function resetClave(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('resetPassword', $usuario);

        if ($usuario->rol === AppRole::Superusuario && auth()->id() !== $usuario->id) {
            return back()->with('error', 'No puede restablecer la clave de otra cuenta de superusuario.');
        }

        $tempPassword = TemporaryPasswordGenerator::generate();

        try {
            Mail::to($usuario->email)->send(new TemporaryPasswordMail(
                $usuario->nombreCompleto(),
                $usuario->email,
                $tempPassword,
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', "No se pudo enviar la clave temporal a {$usuario->email}. La contraseña actual no se modificó.");
        }

        $usuario->update([
            'password' => $tempPassword,
            'force_password_change' => true,
        ]);

        $this->audit->log('UPDATE', $usuario);

        return back()->with('success', "Se envió una clave temporal a {$usuario->email}.");
    }

    public function importCsv(Request $request): RedirectResponse
    {
        $this->authorize('importCsv', User::class);

        $request->validate([
            'archivo_csv' => 'required|file|mimes:csv,txt|max:512',
        ]);

        $contents = (string) file_get_contents($request->file('archivo_csv')->getRealPath());
        $result = $this->importService->importFromCsv($contents);

        if ($result['created'] === 0 && $result['errors'] !== []) {
            return back()->with('error', implode(' ', array_slice($result['errors'], 0, 5)));
        }

        $mensaje = "Importación completada: {$result['created']} cuenta(s) creada(s).";
        if ($result['errors'] !== []) {
            $mensaje .= ' Advertencias: '.implode(' ', array_slice($result['errors'], 0, 3));
        }
        $mensaje .= ' Use «Restablecer clave» si el usuario debe recibir acceso.';

        return back()->with('success', $mensaje);
    }

    public function delegate(Request $request, User $usuario): RedirectResponse
    {
        $this->authorize('delegate', $usuario);

        $actor = $request->user();
        $permitidas = array_map(
            static fn (CapabilityType $capability): string => $capability->value,
            DelegacionDecano::capacidadesQuePuedeOtorgar($actor),
        );

        $validated = $request->validate([
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['string', Rule::in($permitidas)],
        ], [
            'capabilities.*.in' => 'Selecciona una capacidad válida.',
        ]);

        $seleccionadas = collect($validated['capabilities'] ?? [])
            ->map(static fn (string $value): CapabilityType => CapabilityType::from($value))
            ->unique(static fn (CapabilityType $capability): string => $capability->value)
            ->values();

        $defaults = array_map(
            static fn (CapabilityType $capability): string => $capability->value,
            $usuario->rol->defaultCapabilities(),
        );
        $alcance = array_values(array_filter(
            $permitidas,
            static fn (string $value): bool => ! in_array($value, $defaults, true),
        ));
        $mantener = $seleccionadas
            ->map(static fn (CapabilityType $capability): string => $capability->value)
            ->reject(static fn (string $value): bool => in_array($value, $defaults, true))
            ->values()
            ->all();

        if ($alcance !== []) {
            $quitar = UserCapability::query()
                ->where('user_id', $usuario->id)
                ->whereIn('capability', $alcance);

            if ($mantener !== []) {
                $quitar->whereNotIn('capability', $mantener);
            }

            $quitar->delete();
        }

        foreach ($seleccionadas as $capability) {
            if (in_array($capability->value, $defaults, true)) {
                continue;
            }

            $yaExiste = UserCapability::query()
                ->where('user_id', $usuario->id)
                ->where('capability', $capability->value)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            UserCapability::query()->create([
                'user_id' => $usuario->id,
                'capability' => $capability,
                'otorgado_por' => $actor->id,
                'created_at' => now(),
            ]);
        }

        $this->audit->log('UPDATE', $usuario);

        return back()->with('success', 'Funciones actualizadas. Lo que esta persona haga con una función del Decano quedará a nombre del Decano.');
    }
}
