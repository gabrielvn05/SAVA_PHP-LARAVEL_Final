<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Services\AppNotificationService;
use App\Services\AuditService;
use App\Services\OficioDocxService;
use App\Services\OficioPdfService;
use App\Services\SolicitudOficioFirmadoService;
use App\Services\SolicitudWorkflowService;
use App\Support\AdjuntoPreview;
use App\Support\SolicitudAdjuntos;
use App\Support\SolicitudTimeline;
use App\Support\SolicitudValidator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SolicitudController extends Controller
{
    public function __construct(
        private readonly SolicitudWorkflowService $workflow,
        private readonly AuditService $audit,
        private readonly OficioDocxService $oficioDocx,
        private readonly OficioPdfService $oficioPdf,
        private readonly AppNotificationService $appNotifications,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Solicitud::class);

        $query = Solicitud::query()
            ->with('creador')
            ->where('creado_por', auth()->id());

        if ($estado = $request->string('estado')->toString()) {
            $query->where('estado', $estado);
        }

        if ($tipo = $request->string('tipo')->toString()) {
            $query->where('tipo', $tipo);
        }

        if ($q = trim($request->string('q')->toString())) {
            $query->where('motivo', 'ilike', "%{$q}%");
        }

        $solicitudes = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        return view('solicitudes.index', [
            'solicitudes' => $solicitudes,
            'tipos' => SolicitudTipo::cases(),
            'estados' => SolicitudEstado::cases(),
            'filtros' => $request->only(['estado', 'tipo', 'q']),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Solicitud::class);

        return view('solicitudes.create', [
            'tipos' => SolicitudTipo::cases(),
        ]);
    }

    public function wizard(): View
    {
        $this->authorize('create', Solicitud::class);

        return view('solicitudes.wizard', [
            'tipos' => SolicitudTipo::cases(),
            'user' => auth()->user(),
            'minFecha' => now()->subMonths(3)->toDateString(),
            'fechaHoy' => now()->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Solicitud::class);

        $user = auth()->user();

        if ($error = SolicitudValidator::perfilInstitucionalCompleto($user)) {
            return back()->withInput()->with('error', $error);
        }

        if (\App\Support\SolicitudAdjuntoLimits::exceedsTotalLimit($request)) {
            return back()->withInput()->with('error', \App\Support\SolicitudAdjuntoLimits::totalLimitMessage());
        }

        if (\App\Support\SolicitudAdjuntoLimits::exceedsFileCount(0, $request)) {
            return back()->withInput()->with('error', \App\Support\SolicitudAdjuntoLimits::fileCountMessage());
        }

        $validated = $request->validate([
            'tipo' => ['required', Rule::enum(SolicitudTipo::class)],
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'motivo' => 'nullable|string|max:5000',
            'justificativo' => \App\Support\SolicitudAdjuntoLimits::uploadedFileRules(),
            'anexos.*' => \App\Support\SolicitudAdjuntoLimits::uploadedFileRules(),
            'destino' => 'nullable|string|max:500',
            'jornada' => 'nullable|string|max:60',
            'hora_inicio' => 'nullable|string|max:10',
            'hora_fin' => 'nullable|string|max:10',
            'fecha_permiso' => 'nullable|date',
            'hora_inicio_permiso' => 'nullable|string|max:10',
            'hora_fin_permiso' => 'nullable|string|max:10',
            'motivo_permiso' => 'nullable|string|max:80',
            'motivo_permiso_otro' => 'nullable|string|max:500',
            'fecha_atraso' => 'nullable|date',
            'hora_llegada_establecida' => 'nullable|string|max:10',
            'hora_llegada_real' => 'nullable|string|max:10',
            'motivo_atraso' => 'nullable|string|max:80',
            'fecha_inasistencia' => 'nullable|date',
            'fecha_inicio_viaje' => 'nullable|date',
            'fecha_fin_viaje' => 'nullable|date',
            'evento_inicio_dia' => 'nullable|integer|min:1|max:31',
            'evento_inicio_mes' => 'nullable|integer|min:1|max:12',
            'evento_inicio_anio' => 'nullable|integer|min:2000|max:2100',
            'evento_fin_dia' => 'nullable|integer|min:1|max:31',
            'evento_fin_mes' => 'nullable|integer|min:1|max:12',
            'evento_fin_anio' => 'nullable|integer|min:2000|max:2100',
            'fecha_incidente' => 'nullable|date',
            'institucion_medica_tipo' => 'nullable|string|max:120',
            'institucion_medica_nombre' => 'nullable|string|max:255',
            'medico_tratante' => 'nullable|string|max:255',
            'fecha_emision_certificado' => 'nullable|date',
            'dias_reposo' => 'nullable|string|max:10',
            'diagnostico' => 'nullable|string|max:5000',
            'observaciones' => 'nullable|string|max:5000',
            'tipo_viaje_evento' => 'nullable|string|max:80',
            'nombre_evento' => 'nullable|string|max:255',
            'lugar_evento' => 'nullable|string|max:255',
            'rol_especifico' => 'nullable|string|max:255',
            'tipo_calamidad' => 'nullable|string|max:80',
            'nombre_familiar' => 'nullable|string|max:255',
            'parentesco' => 'nullable|string|max:80',
            'descripcion_hecho' => 'nullable|string|max:5000',
            'lugar_suceso' => 'nullable|string|max:255',
            'fecha_hecho' => 'nullable|date',
            'tipo_marcacion_omitida' => 'nullable|string|max:40',
            'hora_real_ingreso' => 'nullable|string|max:10',
            'hora_real_salida' => 'nullable|string|max:10',
            'motivo_falta_registro' => 'nullable|string|max:80',
        ], \App\Support\SolicitudAdjuntoLimits::mimeValidationMessages());

        $tipo = $validated['tipo'];
        $detalleExtraPorTipo = [];

        if ($tipo === SolicitudTipo::Permiso->value) {
            $permiso = \App\Support\SolicitudPermisoRules::resolve($validated);
            if (isset($permiso['error'])) {
                return back()->withInput()->with('error', $permiso['error']);
            }
            $fechaInicio = $permiso['fecha_inicio'];
            $fechaFin = $permiso['fecha_fin'];
            $motivo = $permiso['motivo'];
            $detalleExtraPorTipo = $permiso['detalle_extra'];
        } elseif ($tipo === SolicitudTipo::Justificacion->value) {
            $justificacion = \App\Support\SolicitudJustificacionAtrasoRules::resolve($validated);
            if (isset($justificacion['error'])) {
                return back()->withInput()->with('error', $justificacion['error']);
            }
            $fechaInicio = $justificacion['fecha_inicio'];
            $fechaFin = $justificacion['fecha_fin'];
            $motivo = $justificacion['motivo'];
            $detalleExtraPorTipo = $justificacion['detalle_extra'];
        } elseif ($tipo === SolicitudTipo::FaltaMarcado->value) {
            $faltaMarcado = \App\Support\SolicitudFaltaMarcadoRules::resolve($validated);
            if (isset($faltaMarcado['error'])) {
                return back()->withInput()->with('error', $faltaMarcado['error']);
            }
            $fechaInicio = $faltaMarcado['fecha_inicio'];
            $fechaFin = $faltaMarcado['fecha_fin'];
            $motivo = $faltaMarcado['motivo'];
            $detalleExtraPorTipo = $faltaMarcado['detalle_extra'];
        } elseif ($tipo === SolicitudTipo::Viaje->value) {
            $viaje = \App\Support\SolicitudViajeRules::resolve($validated);
            if (isset($viaje['error'])) {
                return back()->withInput()->with('error', $viaje['error']);
            }
            $fechaInicio = $viaje['fecha_inicio'];
            $fechaFin = $viaje['fecha_fin'];
            $motivo = $viaje['motivo'];
            $detalleExtraPorTipo = $viaje['detalle_extra'];
        } else {
            $fechaInicio = $validated['fecha_inicio']
                ?? $validated['fecha_inasistencia']
                ?? $validated['fecha_inicio_viaje']
                ?? $validated['fecha_incidente']
                ?? null;
            $fechaFin = $validated['fecha_fin']
                ?? $validated['fecha_fin_viaje']
                ?? $fechaInicio;
        }

        if ($tipo === SolicitudTipo::Enfermedad->value && filled($validated['dias_reposo'] ?? null) && $fechaInicio) {
            $dias = max(0, (int) $validated['dias_reposo']);
            if ($dias > 0) {
                $fechaFin = Carbon::parse($fechaInicio)->addDays($dias)->toDateString();
            }
        }

        if (! $fechaInicio || ! $fechaFin) {
            return back()->withInput()->with('error', 'Completa las fechas del trámite.');
        }

        if (Carbon::parse($fechaFin)->lt(Carbon::parse($fechaInicio))) {
            return back()->withInput()->with('error', 'Las fechas del periodo no son válidas.');
        }

        if (! in_array($tipo, [
            SolicitudTipo::Permiso->value,
            SolicitudTipo::Justificacion->value,
            SolicitudTipo::FaltaMarcado->value,
            SolicitudTipo::Viaje->value,
        ], true)) {
            $motivo = trim((string) ($validated['motivo'] ?? ''));
            if ($motivo === '') {
                $motivo = match ($tipo) {
                    SolicitudTipo::Enfermedad->value => 'Certificado médico: '.($validated['diagnostico'] ?? ''),
                    SolicitudTipo::CalamidadDomestica->value => 'Calamidad doméstica',
                    default => '',
                };
            }

            if ($motivo === '') {
                return back()->withInput()->with('error', 'Completa el motivo del trámite.');
            }
        }

        if ($tipo !== SolicitudTipo::Justificacion->value) {
            if ($error = SolicitudValidator::validateFechaInicioMaxTresMeses($fechaInicio)) {
                return back()->withInput()->with('error', $error);
            }
        }

        if (SolicitudValidator::anexoObligatorioParaTipo($validated['tipo'])
            && ! \App\Support\SolicitudAdjuntoLimits::hasNewUploads($request)) {
            return back()->withInput()->with('error', 'Este tipo de trámite requiere adjuntar un justificativo.');
        }

        $anexos = [];
        foreach ($request->file('anexos', []) as $file) {
            if (! $file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }
            $anexos[] = ['path' => SolicitudAdjuntos::guardar($file), 'nombre' => $file->getClientOriginalName()];
        }

        if ($request->hasFile('justificativo')) {
            $file = $request->file('justificativo');
            $anexos[] = ['path' => SolicitudAdjuntos::guardar($file), 'nombre' => $file->getClientOriginalName()];
        }

        $codigo = \App\Support\OficioCodigo::generar($user, SolicitudTipo::from($tipo));

        $institucionTipo = $validated['institucion_medica_tipo'] ?? null;
        $institucionMedica = $institucionTipo === 'IESS'
            ? 'IESS'
            : ($validated['institucion_medica_nombre'] ?? null);

        $detalle = array_filter(array_merge([
            'codigo_tramite' => $codigo,
            'tipo_personal' => $user->rol->label(),
            'cedula' => $user->cedula,
            'carrera' => $user->carrera,
            'destino' => $validated['destino'] ?? null,
            'jornada' => $validated['jornada'] ?? null,
            'hora_inicio' => $validated['hora_inicio'] ?? null,
            'hora_fin' => $validated['hora_fin'] ?? null,
            'fecha_inasistencia' => $validated['fecha_inasistencia'] ?? null,
            'institucion_medica_tipo' => $institucionTipo,
            'institucion_medica' => $institucionMedica,
            'medico_tratante' => $validated['medico_tratante'] ?? null,
            'fecha_emision_certificado' => $validated['fecha_emision_certificado'] ?? null,
            'dias_reposo' => $validated['dias_reposo'] ?? null,
            'diagnostico' => $validated['diagnostico'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'tipo_viaje_evento' => $validated['tipo_viaje_evento'] ?? null,
            'nombre_evento' => $validated['nombre_evento'] ?? null,
            'lugar_evento' => $validated['lugar_evento'] ?? null,
            'fecha_evento_desde' => $validated['fecha_inicio_viaje'] ?? null,
            'fecha_evento_hasta' => $validated['fecha_fin_viaje'] ?? null,
            'rol_especifico' => $validated['rol_especifico'] ?? null,
            'tipo_calamidad' => $validated['tipo_calamidad'] ?? null,
            'nombre_familiar' => $validated['nombre_familiar'] ?? null,
            'parentesco' => $validated['parentesco'] ?? null,
            'descripcion_hecho' => $validated['descripcion_hecho'] ?? null,
            'lugar_suceso' => $validated['lugar_suceso'] ?? null,
            'fecha_hecho' => $validated['fecha_hecho'] ?? null,
            'fecha_incidente' => $validated['fecha_incidente'] ?? null,
            'tipo_marcacion_omitida' => $validated['tipo_marcacion_omitida'] ?? null,
            'hora_real_ingreso' => $validated['hora_real_ingreso'] ?? null,
            'hora_real_salida' => $validated['hora_real_salida'] ?? null,
            'motivo_falta_registro' => $validated['motivo_falta_registro'] ?? null,
            'anexos' => $anexos,
            'anexo_path' => $anexos[0]['path'] ?? null,
            'anexo_nombre' => $anexos[0]['nombre'] ?? null,
        ], $detalleExtraPorTipo), static fn ($value) => $value !== null && $value !== '');

        if (($validated['tipo'] ?? '') === SolicitudTipo::FaltaMarcado->value && ! empty($validated['jornada'])) {
            $user->update(['jornada' => $validated['jornada']]);
        }

        $estado = match (true) {
            $this->workflow->solicitudCreadaPorDecano($user->rol) => SolicitudEstado::Aprobada,
            $user->rol === AppRole::Secretaria => SolicitudEstado::PendienteAprobacionDecano,
            default => SolicitudEstado::EnBorrador,
        };

        $solicitud = Solicitud::create([
            'creado_por' => $user->id,
            'tipo' => $validated['tipo'],
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'motivo' => $motivo,
            'detalle' => $detalle,
            'justificativo_path' => $anexos[0]['path'] ?? null,
            'justificativo_nombre' => $anexos[0]['nombre'] ?? null,
            'estado' => $estado,
            'firmado_por' => $this->workflow->solicitudCreadaPorDecano($user->rol) ? $user->id : null,
            'fecha_firma' => $this->workflow->solicitudCreadaPorDecano($user->rol) ? now() : null,
        ]);

        $this->audit->log('INSERT', $solicitud);
        $this->appNotifications->onSolicitudCreada($solicitud);

        if ($estado === SolicitudEstado::EnBorrador && $this->mostrarFlujoFirmaDocente($solicitud)) {
            return redirect()->route('solicitudes.firmar', $solicitud)
                ->with('success', 'Datos registrados. Firma el oficio y envíalo a Secretaría.');
        }

        if ($estado === SolicitudEstado::EnBorrador) {
            return redirect()->route('solicitudes.edit', $solicitud)
                ->with('success', 'Solicitud guardada. Completa el trámite cuando estés listo.');
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud registrada correctamente.');
    }

    public function show(Solicitud $solicitud): View
    {
        $this->authorize('view', $solicitud);
        $solicitud->load(['creador', 'revisor', 'firmante']);

        $documentos = SolicitudAdjuntos::documentosParaVista($solicitud);

        $user = auth()->user();
        $esStaff = in_array($user->rol, [AppRole::Secretaria, AppRole::Decano, AppRole::Superusuario], true)
            || $user->hasCapability(CapabilityType::RevisarSolicitudes)
            || $user->hasCapability(CapabilityType::AprobarSolicitudes);

        $oficioUsaPlantillaDocx = $this->oficioDocx->puedeGenerarDocx($solicitud);
        $oficioPreviewUrl = route('solicitudes.preview-oficio', $solicitud);
        $oficioDescargarUrl = Route::has('solicitudes.oficio-descargar') && $oficioUsaPlantillaDocx
            ? route('solicitudes.oficio-descargar', $solicitud)
            : $oficioPreviewUrl;

        $mostrarOficioInstitucional = $solicitud->estado === SolicitudEstado::Aprobada
            || $solicitud->creado_por === $user->id
            || ($esStaff && $solicitud->estado !== SolicitudEstado::EnBorrador);

        return view('solicitudes.show', [
            'solicitud' => $solicitud,
            'timeline' => SolicitudTimeline::for($solicitud),
            'documentos' => $documentos,
            'esStaff' => $esStaff,
            'mostrarOficioInstitucional' => $mostrarOficioInstitucional,
            'puedeActuarSecretaria' => $user->can('revisar', $solicitud) && $solicitud->creado_por !== $user->id,
            'puedeActuarDecano' => $user->can('aprobar', $solicitud) && $solicitud->creado_por !== $user->id,
            'oficioUsaPlantillaDocx' => $oficioUsaPlantillaDocx,
            'oficioVistaPdf' => $this->oficioPdf->puedeMostrarPdfEnVisor($solicitud),
            'oficioPreviewUrl' => $oficioPreviewUrl,
            'oficioDescargarUrl' => $oficioDescargarUrl,
            'mostrarFirmaDocente' => $this->mostrarFlujoFirmaDocente($solicitud),
            'oficioDocxUrl' => route('solicitudes.oficio-documento', $solicitud),
            'oficioUsaDocx' => $this->oficioDocx->puedeGenerarDocx($solicitud),
        ]);
    }

    /** @return array{oficioPreviewUrl: string, oficioDescargarUrl: string, oficioDocxUrl: string, oficioUsaDocx: bool} */
    private function urlsOficio(Solicitud $solicitud): array
    {
        $oficioUsaDocx = $this->oficioDocx->puedeGenerarDocx($solicitud);
        $oficioPreviewUrl = route('solicitudes.preview-oficio', $solicitud);
        $oficioDescargarUrl = Route::has('solicitudes.oficio-descargar') && $oficioUsaDocx
            ? route('solicitudes.oficio-descargar', $solicitud)
            : $oficioPreviewUrl;

        return [
            'oficioPreviewUrl' => $oficioPreviewUrl,
            'oficioDescargarUrl' => $oficioDescargarUrl,
            'oficioDocxUrl' => route('solicitudes.oficio-documento', $solicitud),
            'oficioUsaDocx' => $oficioUsaDocx,
        ];
    }

    private function mostrarFlujoFirmaDocente(Solicitud $solicitud): bool
    {
        if ($solicitud->creado_por !== auth()->id()) {
            return false;
        }

        return in_array($solicitud->estado, [SolicitudEstado::EnBorrador, SolicitudEstado::EnRevisionSecretaria], true)
            && $this->oficioDocx->puedeGenerarDocx($solicitud);
    }

    public function adjunto(Request $request, Solicitud $solicitud): Response
    {
        $this->authorize('view', $solicitud);

        $path = str_replace('\\', '/', trim((string) $request->query('f', '')));
        if (! SolicitudAdjuntos::perteneceASolicitud($solicitud, $path)) {
            abort(404);
        }

        $diskName = SolicitudAdjuntos::diskConArchivo($path);
        if ($diskName === null) {
            abort(404);
        }
        $disk = Storage::disk($diskName);

        $nombre = $solicitud->justificativo_nombre ?: 'adjunto';
        foreach ($solicitud->detalle['anexos'] ?? [] as $anexo) {
            if (($anexo['path'] ?? null) === $path) {
                $nombre = (string) ($anexo['nombre'] ?? $nombre);
                break;
            }
        }

        return $disk->response($path, $nombre);
    }

    public function edit(Solicitud $solicitud): View
    {
        $this->authorize('update', $solicitud);

        return view('solicitudes.edit', [
            'solicitud' => $solicitud,
            'tipos' => SolicitudTipo::cases(),
            'anexos' => SolicitudAdjuntos::anexosActuales($solicitud),
            'mostrarFirmaDocente' => $this->mostrarFlujoFirmaDocente($solicitud),
            ...$this->urlsOficio($solicitud),
        ]);
    }

    public function update(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('update', $solicitud);

        $fechaInicioInput = trim((string) $request->input('fecha_inicio', ''));
        if (
            $fechaInicioInput !== ''
            && $solicitud->tipo !== SolicitudTipo::Justificacion
            && ($error = SolicitudValidator::validateFechaInicioMaxTresMeses($fechaInicioInput))
        ) {
            return back()->withInput()->with('error', $error);
        }

        if (\App\Support\SolicitudAdjuntoLimits::exceedsTotalLimit($request)) {
            return back()->withInput()->with('error', \App\Support\SolicitudAdjuntoLimits::totalLimitMessage());
        }

        $anexosActuales = SolicitudAdjuntos::anexosActuales($solicitud);
        $restantesTrasEliminar = SolicitudAdjuntos::restantesTrasEliminar($solicitud, $request);
        if (\App\Support\SolicitudAdjuntoLimits::exceedsFileCount($restantesTrasEliminar, $request)) {
            return back()->withInput()->with('error', \App\Support\SolicitudAdjuntoLimits::fileCountMessage());
        }

        $mantenerBorrador = $solicitud->estado === SolicitudEstado::EnBorrador && $request->boolean('borrador');
        $anexosPrevistos = $restantesTrasEliminar + \App\Support\SolicitudAdjuntoLimits::countNewUploads($request);
        if (
            ! $mantenerBorrador
            && SolicitudValidator::anexoObligatorioParaTipo($solicitud->tipo->value)
            && $anexosPrevistos < 1
        ) {
            return back()->withInput()->with('error', 'Este tipo de trámite requiere adjuntar un justificativo.');
        }

        $validated = $request->validate([
            'tipo' => ['required', Rule::enum(SolicitudTipo::class)],
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'motivo' => 'required|string|max:5000',
            'justificativo' => \App\Support\SolicitudAdjuntoLimits::uploadedFileRules(),
            'anexos.*' => \App\Support\SolicitudAdjuntoLimits::uploadedFileRules(),
            'eliminar_anexos.*' => 'nullable|string',
        ], \App\Support\SolicitudAdjuntoLimits::mimeValidationMessages());

        if ($validated['tipo'] !== $solicitud->tipo->value) {
            return back()->withInput()->with('error', 'No puedes cambiar el tipo de solicitud. Crea un trámite nuevo si te equivocaste.');
        }

        $old = $solicitud->toArray();
        $detalle = $solicitud->detalle ?? [];
        $anexos = SolicitudAdjuntos::aplicarCambiosAdjuntos($solicitud, $request, $anexosActuales);
        if ($request->hasFile('oficio_firmado_docente')) {
            $firmado = app(SolicitudOficioFirmadoService::class)->aplicarSubidaManual(
                $solicitud,
                $request->file('oficio_firmado_docente'),
                $anexos,
            );
            $anexos = $firmado['anexos'];
            $detalle['firma_docente'] = $firmado['firma_docente'];
        }
        $legacy = SolicitudAdjuntos::sincronizarCamposLegacy($anexos);

        $detalle['anexos'] = $legacy['detalle_anexos'];
        $detalle['anexo_path'] = $legacy['justificativo_path'];
        $detalle['anexo_nombre'] = $legacy['justificativo_nombre'];

        $data = [
            'tipo' => $solicitud->tipo->value,
            'fecha_inicio' => $validated['fecha_inicio'],
            'fecha_fin' => $validated['fecha_fin'],
            'motivo' => $validated['motivo'],
            'detalle' => $detalle,
            'justificativo_path' => $legacy['justificativo_path'],
            'justificativo_nombre' => $legacy['justificativo_nombre'],
        ];

        $solicitud->update($data);
        $this->audit->log('UPDATE', $solicitud, $old);

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud actualizada.');
    }

    public function destroy(Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('delete', $solicitud);

        $old = $solicitud->toArray();
        if ($solicitud->justificativo_path) {
            SolicitudAdjuntos::borrar($solicitud->justificativo_path);
        }
        foreach (($solicitud->detalle['anexos'] ?? []) as $anexo) {
            if (! empty($anexo['path'])) {
                SolicitudAdjuntos::borrar((string) $anexo['path']);
            }
        }
        $solicitud->delete();
        $this->audit->log('DELETE', $solicitud, $old);

        return redirect()->route('solicitudes.index')
            ->with('success', 'Solicitud eliminada.');
    }
}
