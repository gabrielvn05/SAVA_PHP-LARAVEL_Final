<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Enums\SolicitudEstado;
use App\Services\OficioPdfService;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Services\AppNotificationService;
use App\Services\AuditService;
use App\Services\SolicitudNotificacionService;
use App\Services\SolicitudWorkflowService;
use App\Support\Carreras;
use App\Support\DelegacionDecano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SolicitudProcesoController extends Controller
{
    public function __construct(
        private readonly SolicitudWorkflowService $workflow,
        private readonly AuditService $audit,
        private readonly SolicitudNotificacionService $notificaciones,
        private readonly AppNotificationService $appNotifications,
        private readonly OficioPdfService $oficioPdf,
    ) {
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
        $query = Solicitud::query()->with('creador');

        if ($estado = $request->string('estado')->toString()) {
            $query->where('estado', $estado);
        }

        if ($tipo = $request->string('tipo')->toString()) {
            $query->where('tipo', $tipo);
        }

        $nombre = trim($request->string('nombre')->toString());
        if ($nombre !== '') {
            $like = '%'.mb_strtolower($nombre).'%';
            $query->whereHas('creador', function ($user) use ($like): void {
                $user->where(function ($nombre) use ($like): void {
                    $nombre->whereRaw('LOWER(nombres) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(apellidos) LIKE ?', [$like]);
                });
            });
        }

        $rol = trim($request->string('rol')->toString());
        if ($rol !== '') {
            $query->whereHas('creador', fn ($user) => $user->where('rol', $rol));
        }

        $carrera = trim($request->string('carrera')->toString());
        if ($carrera !== '') {
            $query->whereHas('creador', fn ($user) => $user->where('carrera', $carrera));
        }

        if ($desde = $request->string('fecha_desde')->toString()) {
            $query->whereDate('fecha_inicio', '>=', $desde);
        }

        if ($hasta = $request->string('fecha_hasta')->toString()) {
            $query->whereDate('fecha_inicio', '<=', $hasta);
        }

        $solicitudes = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        return view('solicitudes.proceso', [
            'solicitudes' => $solicitudes,
            'tipos' => SolicitudTipo::cases(),
            'estados' => SolicitudEstado::cases(),
            'carreras' => Carreras::OPCIONES,
            'roles' => AppRole::cases(),
            'filtros' => $request->only(['estado', 'tipo', 'nombre', 'rol', 'carrera', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function revisar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('revisar', $solicitud);

        if ($solicitud->creado_por === auth()->id()) {
            return back()->with('error', 'No puedes revisar una solicitud creada por ti mismo.');
        }

        $validated = $request->validate([
            'aprobado' => 'required|boolean',
            'observaciones_secretaria' => 'nullable|string|max:5000',
        ]);

        $aprobado = $request->boolean('aprobado');

        if ($aprobado && blank($validated['observaciones_secretaria'] ?? null)) {
            $validated['observaciones_secretaria'] = 'Aprobado en revisión de Secretaría.';
        }

        if (! $aprobado && blank($validated['observaciones_secretaria'] ?? null)) {
            return back()->with('error', 'Debe indicar el motivo del rechazo.');
        }

        $old = $solicitud->toArray();
        $revisor = auth()->user();
        $revisorFirmaComoDecano = in_array($revisor->rol, [AppRole::Decano, AppRole::Superusuario], true);
        $nuevoEstado = $this->workflow->estadoTrasRevisionSecretaria(
            $solicitud->creador->rol,
            $aprobado,
            $aprobado && $revisorFirmaComoDecano,
        );

        $firmaFinal = $nuevoEstado === SolicitudEstado::Aprobada;
        $observacionesDecano = $solicitud->observaciones_decano;
        if ($firmaFinal) {
            $observacionesDecano = $revisorFirmaComoDecano && $solicitud->creador->rol !== AppRole::Decano
                ? ($validated['observaciones_secretaria'] ?? null)
                : ($validated['observaciones_secretaria'] ?? null);
        }

        $solicitud->update([
            'estado' => $nuevoEstado,
            'revisado_por' => auth()->id(),
            'observaciones_secretaria' => $validated['observaciones_secretaria'] ?? null,
            'firmado_por' => $firmaFinal ? auth()->id() : null,
            'fecha_firma' => $firmaFinal ? now() : null,
            'observaciones_decano' => $observacionesDecano,
        ]);

        if ($firmaFinal) {
            $this->oficioPdf->invalidarCache($solicitud);
            $this->oficioPdf->generar($solicitud->fresh(['firmante', 'creador']));
        }

        $this->audit->log('UPDATE', $solicitud, $old);
        $this->appNotifications->onSolicitudRevisadaSecretaria(
            $solicitud->fresh(['creador']),
            $nuevoEstado,
            $aprobado,
        );

        $mensaje = 'Revisión registrada.';
        if (in_array($nuevoEstado, [SolicitudEstado::Aprobada, SolicitudEstado::Rechazada], true)) {
            $mensaje = $this->mensajeResultado($nuevoEstado === SolicitudEstado::Aprobada, $solicitud);
        }

        return back()->with('success', $mensaje);
    }

    public function aprobar(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('aprobar', $solicitud);

        if ($solicitud->creado_por === auth()->id()) {
            return back()->with('error', 'No puedes firmar una solicitud creada por ti mismo.');
        }

        $validated = $request->validate([
            'aprobado' => 'required|boolean',
            'observaciones_decano' => 'nullable|string|max:5000',
        ]);

        $aprobado = $request->boolean('aprobado');

        if ($aprobado && blank($validated['observaciones_decano'] ?? null)) {
            $validated['observaciones_decano'] = 'Aprobado y firmado por Decano.';
        }

        if (! $aprobado && blank($validated['observaciones_decano'] ?? null)) {
            return back()->with('error', 'Debe indicar el motivo del rechazo.');
        }

        $actor = auth()->user();
        $firmante = $actor;
        if (DelegacionDecano::esDelegada($actor, CapabilityType::AprobarSolicitudes)) {
            $firmante = DelegacionDecano::decanoTitular($actor, CapabilityType::AprobarSolicitudes);
            if ($firmante === null) {
                return back()->with('error', 'No hay un Decano activo. El visto bueno debe quedar a nombre del Decano.');
            }
        }

        $old = $solicitud->toArray();

        $solicitud->update([
            'estado' => $aprobado ? SolicitudEstado::Aprobada : SolicitudEstado::Rechazada,
            'firmado_por' => $firmante->id,
            'observaciones_decano' => $validated['observaciones_decano'] ?? null,
            'fecha_firma' => now(),
        ]);

        if ($aprobado) {
            $this->oficioPdf->invalidarCache($solicitud);
            $this->oficioPdf->generar($solicitud->fresh(['firmante', 'creador']));
        }

        $this->audit->log('UPDATE', $solicitud, $old);
        $this->appNotifications->onSolicitudResueltaDecano($solicitud->fresh(['creador']), $aprobado);

        return back()->with('success', $this->mensajeResultado($aprobado, $solicitud));
    }

    private function mensajeResultado(bool $aprobado, Solicitud $solicitud): string
    {
        $base = $aprobado ? 'Solicitud aprobada.' : 'Solicitud rechazada.';
        $enviado = $this->notificaciones->enviarResultadoFinal($solicitud->fresh(['creador']));

        if ($enviado) {
            return $base.' Se notificó al solicitante por correo.';
        }

        return $base.' No se pudo enviar el correo de notificación.';
    }
}
