<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditoriaController extends Controller
{
    public function index(Request $request): View
    {
        $this->soloSuperusuario();

        $filtros = $request->validate([
            'usuario' => ['nullable', 'uuid'],
            'accion' => ['nullable', 'string', 'max:32'],
            'tabla' => ['nullable', 'string', 'max:64'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $query = AuditLog::query()
            ->with('changedBy')
            ->orderByDesc('changed_at')
            ->orderByDesc('id');

        if (! empty($filtros['usuario'])) {
            $query->where('changed_by', $filtros['usuario']);
        }

        if (! empty($filtros['accion'])) {
            $query->where('action', $filtros['accion']);
        }

        if (! empty($filtros['tabla'])) {
            $query->where('table_name', $filtros['tabla']);
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('changed_at', '>=', $filtros['desde']);
        }

        if (! empty($filtros['hasta'])) {
            $query->whereDate('changed_at', '<=', $filtros['hasta']);
        }

        if (! empty($filtros['q'])) {
            $term = '%'.str_replace(['%', '_'], '', $filtros['q']).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->where('record_id', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('table_name', 'like', $term)
                    ->orWhere('action', 'like', $term)
                    ->orWhereHas('changedBy', function ($usuarios) use ($term): void {
                        $usuarios->where('nombres', 'like', $term)
                            ->orWhere('apellidos', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('cedula', 'like', $term);
                    });
            });
        }

        $logs = $query->paginate(15)->withQueryString();

        $usuarios = User::query()
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'email', 'rol']);

        $acciones = AuditLog::query()->distinct()->orderBy('action')->pluck('action');
        $tablas = AuditLog::query()->distinct()->orderBy('table_name')->pluck('table_name');

        return view('admin.auditoria.index', [
            'logs' => $logs,
            'usuarios' => $usuarios,
            'acciones' => $acciones,
            'tablas' => $tablas,
            'filtros' => $filtros,
            'total' => AuditLog::query()->count(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->soloSuperusuario();

        $auditLog->load('changedBy');

        return view('admin.auditoria.show', [
            'log' => $auditLog,
        ]);
    }

    private function soloSuperusuario(): void
    {
        abort_unless(auth()->user()?->rol === AppRole::Superusuario, 403);
    }
}
