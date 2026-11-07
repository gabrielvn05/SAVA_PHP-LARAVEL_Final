<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccesoResultado;
use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Models\AccesoEvento;
use App\Models\User;
use App\Services\AccesoEventoService;
use App\Services\SuperusuarioPanelService;
use App\Support\DelegacionDecano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisionController extends Controller
{
    public function __construct(
        private readonly SuperusuarioPanelService $panel,
        private readonly AccesoEventoService $accesos,
    ) {}

    public function accesos(Request $request): View
    {
        $this->soloSuperusuario();

        $filtros = $request->validate([
            'resultado' => ['nullable', 'string', 'max:40'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = AccesoEvento::query()
            ->with(['user', 'actor'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if (! empty($filtros['resultado'])) {
            $query->where('resultado', $filtros['resultado']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('occurred_at', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('occurred_at', '<=', $filtros['hasta']);
        }

        if (! empty($filtros['q'])) {
            $term = '%'.str_replace(['%', '_'], '', $filtros['q']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('email', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhereHas('user', function ($usuarios) use ($term): void {
                        $usuarios->where('nombres', 'like', $term)
                            ->orWhere('apellidos', 'like', $term)
                            ->orWhere('cedula', 'like', $term);
                    });
            });
        }

        return view('admin.supervision.accesos', [
            'eventos' => $query->paginate(15)->withQueryString(),
            'sesiones' => $this->panel->sesionesAbiertas(),
            'usaSesionesEnBase' => $this->panel->usaSesionesEnBase(),
            'cuentas' => User::query()->where('id', '!=', auth()->id())->orderBy('apellidos')->orderBy('nombres')->get(),
            'resultados' => AccesoResultado::cases(),
            'filtros' => $filtros,
        ]);
    }

    public function cerrarSesion(Request $request): RedirectResponse
    {
        $this->soloSuperusuario();

        $usuario = User::query()->findOrFail($request->validate([
            'usuario_id' => ['required', 'uuid'],
        ])['usuario_id']);

        if ($usuario->id === auth()->id()) {
            return back()->with('error', 'No puede cerrar su propia sesión desde aquí. Use Cerrar sesión.');
        }

        $this->panel->cerrarSesion($usuario);
        $this->accesos->registrar(
            AccesoResultado::SesionCerrada,
            $usuario,
            $usuario->email,
            'sistema',
            $request->user(),
        );

        return back()->with('success', 'Se cerró la sesión de '.$usuario->nombreCompleto().'.');
    }

    public function cuentas(): View
    {
        $this->soloSuperusuario();

        return view('admin.supervision.cuentas', [
            'inactivas' => $this->panel->cuentasInactivas(),
            'claves' => $this->panel->clavesPendientes(),
            'altas' => $this->panel->altasPendientes(),
            'totalInactivas' => User::query()->where('activo', false)->count(),
            'totalClaves' => User::query()->where('force_password_change', true)->count(),
            'totalAltas' => $this->panel->contadores()['altas'],
        ]);
    }

    public function delegaciones(): View
    {
        $this->soloSuperusuario();

        $delegaciones = $this->panel->delegaciones();

        return view('admin.supervision.delegaciones', [
            'delegaciones' => $delegaciones,
            'titulares' => $delegaciones->mapWithKeys(function ($capacidad) {
                $usuario = $capacidad->user;
                $capability = $capacidad->capability;
                $titular = $usuario && $capability
                    ? DelegacionDecano::decanoTitular($usuario, $capability)
                    : null;

                return [$capacidad->user_id.'|'.$capability->value => $titular];
            }),
        ]);
    }

    private function soloSuperusuario(): void
    {
        abort_unless(auth()->user()?->rol === AppRole::Superusuario, 403);
    }
}
