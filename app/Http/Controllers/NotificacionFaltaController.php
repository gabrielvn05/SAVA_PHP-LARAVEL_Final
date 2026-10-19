<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Models\NotificacionFalta;
use App\Models\User;
use App\Services\NotificacionFaltaCorreoService;
use App\Services\NotificacionFaltaDocxService;
use App\Services\NotificacionFaltaImportService;
use App\Services\NotificacionFaltaService;
use App\Services\OficioPdfService;
use App\Support\CorreoEnvioResultado;
use App\Support\NotificacionFaltaMarcacion;
use App\Support\ViteManifest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NotificacionFaltaController extends Controller
{
    public function __construct(
        private readonly NotificacionFaltaService $service,
        private readonly NotificacionFaltaImportService $importService,
        private readonly NotificacionFaltaDocxService $docxService,
        private readonly NotificacionFaltaCorreoService $correoService,
        private readonly OficioPdfService $pdfService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', NotificacionFalta::class);

        $query = NotificacionFalta::query()
            ->with(['destinatario', 'decano', 'emitidoPor'])
            ->orderByDesc('created_at');

        if ($q = trim($request->string('q')->toString())) {
            $query->whereHas('destinatario', function ($sub) use ($q): void {
                $sub->where('nombres', 'ilike', "%{$q}%")
                    ->orWhere('apellidos', 'ilike', "%{$q}%")
                    ->orWhere('cedula', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%");
            });
        }

        $notificaciones = $query->limit(100)->get();

        $stats = [
            'total' => NotificacionFalta::query()->count(),
            'pendientes' => NotificacionFalta::query()->where('tiene_justificacion_aprobada', false)->count(),
            'justificadas' => NotificacionFalta::query()->where('tiene_justificacion_aprobada', true)->count(),
        ];

        $colaboradores = User::query()
            ->where('rol', '!=', AppRole::Superusuario)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get(['id', 'nombres', 'apellidos', 'cedula', 'rol', 'email', 'activo']);

        return view('notificaciones-faltas.index', [
            'notificaciones' => $notificaciones,
            'colaboradores' => $colaboradores,
            'tiposMarcacion' => NotificacionFaltaMarcacion::valores(),
            'filtroQ' => $request->string('q')->toString(),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', NotificacionFalta::class);

        $validated = $request->validate([
            'destinatario_id' => 'required|uuid|exists:users,id',
            'fecha_falta' => 'required|date',
            'tipo_marcacion' => 'required|in:entrada,salida,entrada_salida',
            'notas' => 'nullable|string|max:2000',
        ]);

        $notificacion = $this->service->crear($request->user(), $validated);
        $correo = $this->correoService->enviarAvisoColaborador($notificacion);

        $mensaje = $this->mensajeFlashNotificacionCorreo($correo);
        $flash = $correo->enviadoAlBuzon ? 'success' : 'warning';

        return redirect()
            ->route('notificaciones-faltas.index')
            ->with($flash, $mensaje);
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', NotificacionFalta::class);

        $request->validate([
            'archivo_csv' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $result = $this->importService->importFromCsv(
            $request->file('archivo_csv'),
            $request->user(),
            $this->correoService,
        );

        $mensaje = "Importación: {$result['created']} notificación(es) creada(s).";
        if ($result['emails_enviados'] > 0) {
            $mensaje .= " {$result['emails_enviados']} correo(s) entregado(s) al buzón.";
        }
        if ($result['emails_fallidos'] > 0) {
            $mensaje .= " {$result['emails_fallidos']} correo(s) no entregados (revise MAIL_* o perfil del colaborador).";
        }
        if ($result['skipped'] > 0) {
            $mensaje .= " {$result['skipped']} duplicada(s) omitida(s).";
        }
        if ($result['errors'] !== []) {
            $mensaje .= ' Advertencias: '.implode(' ', array_slice($result['errors'], 0, 3));
        }

        return redirect()
            ->route('notificaciones-faltas.index')
            ->with($result['created'] > 0 ? 'success' : 'error', $mensaje);
    }

    public function reenviarCorreo(NotificacionFalta $notificacionFalta): RedirectResponse
    {
        $this->authorize('view', $notificacionFalta);

        $correo = $this->correoService->enviarAvisoColaborador($notificacionFalta);

        return redirect()
            ->route('notificaciones-faltas.show', $notificacionFalta)
            ->with($correo->flashTipo(), $correo->mensajeUsuario);
    }

    public function show(NotificacionFalta $notificacionFalta): View
    {
        $this->authorize('view', $notificacionFalta);
        $notificacionFalta->load(['destinatario', 'decano', 'emitidoPor']);

        return view('notificaciones-faltas.show', [
            'notificacion' => $notificacionFalta,
            'vistaPdf' => $this->pdfOriginal($notificacionFalta) !== null,
        ]);
    }

    public function preview(NotificacionFalta $notificacionFalta): View|Response
    {
        $this->authorize('view', $notificacionFalta);

        $pdf = $this->pdfOriginal($notificacionFalta);
        if (is_string($pdf) && $pdf !== '') {
            $nombre = $notificacionFalta->codigo_oficio.'.pdf';

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$nombre.'"',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        }

        return view('certificado.oficio-preview-shell', [
            'solicitud' => null,
            'docxUrl' => route('notificaciones-faltas.documento', $notificacionFalta),
            'descargarUrl' => route('notificaciones-faltas.descargar', $notificacionFalta),
            'htmlFallbackUrl' => '',
            'codigo' => $notificacionFalta->codigo_oficio,
            'avisoAproximado' => true,
            'pdfNoDisponible' => true,
            'oficioPreviewScriptUrl' => ViteManifest::moduleUrl('resources/js/oficio-preview.js'),
        ]);
    }

    public function documento(NotificacionFalta $notificacionFalta): Response
    {
        $this->authorize('view', $notificacionFalta);

        $binary = $this->docxService->generar($notificacionFalta);
        $nombre = $notificacionFalta->codigo_oficio.'.docx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function descargar(NotificacionFalta $notificacionFalta): Response
    {
        $this->authorize('view', $notificacionFalta);

        $binary = $this->docxService->generar($notificacionFalta);
        $nombre = $notificacionFalta->codigo_oficio.'.docx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    private function pdfOriginal(NotificacionFalta $notificacion): ?string
    {
        $cache = storage_path('app/oficios/pdf/notificacion-'.$notificacion->id.'.'.OficioPdfService::PLANTILLA_VERSION.'.pdf');
        $plantilla = $this->docxService->plantillaPath();
        $actualizado = $notificacion->updated_at?->getTimestamp() ?? 0;

        if (is_file($cache) && filemtime($cache) >= $actualizado && ($plantilla === null || filemtime($plantilla) <= filemtime($cache))) {
            $desdeCache = file_get_contents($cache);

            return is_string($desdeCache) && $desdeCache !== '' ? $desdeCache : null;
        }

        try {
            $docx = $this->docxService->generar($notificacion);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        $pdf = $this->pdfService->convertir($docx);
        if (! is_string($pdf) || $pdf === '') {
            return null;
        }

        File::ensureDirectoryExists(dirname($cache));
        file_put_contents($cache, $pdf);

        return $pdf;
    }

    private function mensajeFlashNotificacionCorreo(CorreoEnvioResultado $correo): string
    {
        $mensaje = 'Notificación registrada. Puede previsualizar y descargar el oficio. '.$correo->mensajeUsuario;

        if (! $correo->enviadoAlBuzon && filled($correo->errorTecnico)) {
            $mensaje .= ' Detalle: '.Str::limit($correo->errorTecnico, 220);
        }

        return $mensaje;
    }
}
