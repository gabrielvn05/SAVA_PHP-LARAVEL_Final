<?php

namespace App\Http\Controllers;

use App\Enums\CapabilityType;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Services\ReporteExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function __construct(private readonly ReporteExportService $exportService)
    {
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (! $user->hasCapability(CapabilityType::RevisarSolicitudes)
                && ! $user->hasCapability(CapabilityType::AprobarSolicitudes)) {
                abort(403);
            }

            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);
        $solicitudes = (clone $query)->with('creador')->orderByDesc('created_at')->limit(50)->get();

        return view('secretaria.reportes.index', [
            'solicitudes' => $solicitudes,
            'total' => (clone $query)->count(),
            'tipos' => SolicitudTipo::cases(),
            'estados' => SolicitudEstado::cases(),
            'filtros' => $request->only(['tipo', 'estado', 'desde', 'hasta', 'q']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $solicitudes = $this->filteredQuery($request)
            ->with('creador')
            ->orderByDesc('created_at')
            ->get();

        return $this->exportService->exportGeneral($solicitudes);
    }

    private function filteredQuery(Request $request)
    {
        $query = Solicitud::query();

        if ($tipo = $request->string('tipo')->toString()) {
            $query->where('tipo', $tipo);
        }

        if ($estado = $request->string('estado')->toString()) {
            $query->where('estado', $estado);
        }

        if ($desde = $request->date('desde')) {
            $query->whereDate('fecha_inicio', '>=', $desde);
        }

        if ($hasta = $request->date('hasta')) {
            $query->whereDate('fecha_fin', '<=', $hasta);
        }

        if ($q = trim($request->string('q')->toString())) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('motivo', 'ilike', "%{$q}%")
                    ->orWhereHas('creador', function ($user) use ($q): void {
                        $user->where('nombres', 'ilike', "%{$q}%")
                            ->orWhere('apellidos', 'ilike', "%{$q}%")
                            ->orWhere('email', 'ilike', "%{$q}%");
                    });
            });
        }

        return $query;
    }
}
