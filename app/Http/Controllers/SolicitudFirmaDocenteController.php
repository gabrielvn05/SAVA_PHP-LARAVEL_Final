<?php

namespace App\Http\Controllers;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Services\AppNotificationService;
use App\Services\AuditService;
use App\Services\OficioDocxService;
use App\Services\OficioPdfService;
use App\Models\FirmaEcEnvio;
use App\Models\User;
use App\Services\FirmaEcService;
use App\Services\SolicitudOficioFirmadoService;
use App\Services\SolicitudWorkflowService;
use App\Support\OficioNombreArchivo;
use App\Support\SolicitudAdjuntos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SolicitudFirmaDocenteController extends Controller
{
    public function __construct(
        private readonly SolicitudOficioFirmadoService $oficioFirmado,
        private readonly FirmaEcService $firmaEc,
        private readonly OficioDocxService $oficioDocx,
        private readonly OficioPdfService $oficioPdf,
        private readonly SolicitudWorkflowService $workflow,
        private readonly AuditService $audit,
        private readonly AppNotificationService $appNotifications,
    ) {}

    public function show(Request $request, Solicitud $solicitud): View
    {
        $this->authorize('firmarOficio', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);
        $solicitud->refresh();

        if (! $this->oficioDocx->puedeGenerarDocx($solicitud)) {
            abort(404, 'Este trámite no admite firma de oficio en línea.');
        }

        $modo = $request->query('modo');
        if (! in_array($modo, ['p12', 'manual'], true)) {
            $modo = null;
        }

        if ($modo !== null) {
            $this->oficioPdf->generarParaFirma($solicitud);
        }

        $oficioUsaDocx = $this->oficioDocx->puedeGenerarDocx($solicitud);
        $oficioPreviewUrl = route('solicitudes.preview-oficio', $solicitud);
        $oficioDescargarUrl = Route::has('solicitudes.oficio-descargar') && $oficioUsaDocx
            ? route('solicitudes.oficio-descargar', $solicitud)
            : $oficioPreviewUrl;

        return view('solicitudes.firmar', [
            'solicitud' => $solicitud,
            'modo' => $modo,
            'pdfFirmaUrl' => route('solicitudes.oficio-pdf-firma', $solicitud),
            'oficioPreviewUrl' => $oficioPreviewUrl,
            'oficioDescargarUrl' => $oficioDescargarUrl,
            'oficioDocxUrl' => route('solicitudes.oficio-documento', $solicitud),
            'oficioUsaDocx' => $oficioUsaDocx,
        ]);
    }

    public function pdfFirma(Solicitud $solicitud): Response
    {
        $this->authorize('view', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        $pdf = $this->oficioPdf->generarParaFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            abort(503, 'No se pudo generar el PDF del oficio.');
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-para-firma.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function firmarNavegador(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('firmarOficio', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        $validated = $request->validate([
            'firma_imagen' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'firma_page' => ['required', 'integer', 'min:1', 'max:50'],
            'firma_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'firma_y' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $imagen = $request->file('firma_imagen');
        if ($imagen === null) {
            abort(422);
        }

        $anexos = SolicitudAdjuntos::anexosActuales($solicitud);
        $firmado = $this->oficioFirmado->firmarConImagenNavegador(
            $solicitud,
            $imagen,
            (int) $validated['firma_page'],
            (float) $validated['firma_x'],
            (float) $validated['firma_y'],
            $anexos,
        );

        $legacy = SolicitudAdjuntos::sincronizarCamposLegacy($firmado['anexos']);
        $detalle = $solicitud->detalle ?? [];
        $detalle['anexos'] = $legacy['detalle_anexos'];
        $detalle['firma_docente'] = $firmado['firma_docente'];
        $solicitud->update([
            'detalle' => $detalle,
            'justificativo_path' => $legacy['justificativo_path'],
            'justificativo_nombre' => $legacy['justificativo_nombre'],
        ]);

        return $this->redirectTrasGuardarFirma($solicitud, $firmado, 'Firma aplicada al oficio. Revisa el documento y envía a Secretaría cuando esté listo.');
    }

    public function iniciarFirmaEc(Request $request, Solicitud $solicitud): JsonResponse
    {
        $this->authorize('firmarOficio', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        $validated = $request->validate([
            'tipo_certificado' => ['required', 'integer', 'in:1,2,3'],
            'firma_page' => ['required', 'integer', 'min:1', 'max:50'],
            'firma_x' => ['required', 'numeric', 'min:0', 'max:100'],
            'firma_y' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $usuario = $request->user();
        if (! $usuario instanceof User) {
            abort(403);
        }

        $preparado = $this->firmaEc->preparar(
            $solicitud,
            $usuario,
            (int) $validated['firma_page'],
            (float) $validated['firma_x'],
            (float) $validated['firma_y'],
            (int) $validated['tipo_certificado'],
        );

        return response()->json([
            'estado' => 'pendiente',
            'envio_id' => $preparado['envio']->id,
            'enlace' => $preparado['enlace'],
            'estado_url' => route('solicitudes.firmar-firmaec.estado', [$solicitud, $preparado['envio']]),
        ]);
    }

    public function estadoFirmaEc(Solicitud $solicitud, FirmaEcEnvio $envio): JsonResponse
    {
        $this->authorize('firmarOficio', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        if ($envio->solicitud_id !== $solicitud->id || $envio->user_id !== auth()->id()) {
            abort(404);
        }

        $envio->refresh();
        if ($envio->estado === 'firmado') {
            return response()->json([
                'estado' => 'firmado',
                'redirect' => route('solicitudes.firmar', $solicitud).'#vista-firmado',
            ]);
        }

        return response()->json([
            'estado' => $envio->estado,
            'mensaje' => $envio->error,
        ]);
    }

    public function firmarP12Documento(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('firmarOficio', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        $request->validate([
            'p12' => ['required', 'file', 'max:5120'],
            'p12_password' => ['required', 'string', 'max:255'],
            'firma_imagen' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'firma_page' => ['required_without:firma_imagen', 'integer', 'min:1', 'max:50'],
            'firma_x' => ['required_without:firma_imagen', 'numeric', 'min:0', 'max:100'],
            'firma_y' => ['required_without:firma_imagen', 'numeric', 'min:0', 'max:100'],
        ]);

        $p12 = $request->file('p12');
        if ($p12 === null) {
            abort(422);
        }

        $anexos = SolicitudAdjuntos::anexosActuales($solicitud);
        $imagen = $request->file('firma_imagen');

        if ($imagen !== null) {
            $firmado = $this->oficioFirmado->firmarConP12EImagenEnDocumento(
                $solicitud,
                $p12,
                (string) $request->input('p12_password'),
                $imagen,
                (int) $request->input('firma_page', 1),
                (float) $request->input('firma_x', 75),
                (float) $request->input('firma_y', 88),
                $anexos,
            );

            return $this->redirectTrasGuardarFirma($solicitud, $firmado, $this->mensajeTrasFirmaP12($solicitud));
        }

        $firmado = $this->oficioFirmado->firmarConP12EnPosicion(
            $solicitud,
            $p12,
            (string) $request->input('p12_password'),
            (int) $request->input('firma_page', 1),
            (float) $request->input('firma_x', 75),
            (float) $request->input('firma_y', 88),
            $anexos,
        );

        return $this->redirectTrasGuardarFirma(
            $solicitud,
            $firmado,
            $this->mensajeTrasFirmaP12($solicitud),
        );
    }

    public function subirManual(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('firmarOficio', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        $request->validate([
            'oficio_firmado_docente' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg'],
        ]);

        $file = $request->file('oficio_firmado_docente');
        if ($file === null) {
            abort(422);
        }

        $anexos = SolicitudAdjuntos::anexosActuales($solicitud);
        $firmado = $this->oficioFirmado->aplicarSubidaManual($solicitud, $file, $anexos);

        return $this->redirectTrasGuardarFirma(
            $solicitud,
            $firmado,
            'Oficio firmado guardado. Revisa la vista previa y envía a Secretaría cuando esté listo.',
        );
    }

    private function mensajeTrasFirmaP12(Solicitud $solicitud): string
    {
        if ($solicitud->estado === SolicitudEstado::Aprobada) {
            return 'Oficio firmado. El sello de autorizado quedó dentro de la firma. Descárgalo de nuevo y valídalo en FirmaEC.';
        }

        return 'Oficio firmado con certificado .p12. Revisa la vista previa y, si está correcto, envía a Secretaría. El sello se estampa en otra firma, después de que el decano apruebe.';
    }

    /**
     * @param  array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}  $firmado
     */
    private function redirectTrasGuardarFirma(Solicitud $solicitud, array $firmado, string $mensaje): RedirectResponse
    {
        $legacy = SolicitudAdjuntos::sincronizarCamposLegacy($firmado['anexos']);
        $detalle = $solicitud->detalle ?? [];
        $detalle['anexos'] = $legacy['detalle_anexos'];
        $detalle['firma_docente'] = $firmado['firma_docente'];
        $solicitud->update([
            'detalle' => $detalle,
            'justificativo_path' => $legacy['justificativo_path'],
            'justificativo_nombre' => $legacy['justificativo_nombre'],
        ]);

        $solicitud->refresh();
        if (! SolicitudOficioFirmadoService::tieneFirmaDocente($solicitud)) {
            return redirect()
                ->route('solicitudes.firmar', ['solicitud' => $solicitud, 'modo' => 'p12'])
                ->with('error', 'No se pudo guardar el oficio firmado. Intenta de nuevo o usa la subida manual.');
        }

        return redirect()
            ->to(route('solicitudes.firmar', $solicitud).'#vista-firmado')
            ->with('success', $mensaje)
            ->with('firma_aplicada', true);
    }

    public function enviarSecretaria(Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('update', $solicitud);

        if ($solicitud->creado_por !== auth()->id()) {
            abort(403);
        }

        if ($solicitud->estado !== SolicitudEstado::EnBorrador) {
            abort(403, 'Este trámite ya fue enviado o no admite envío desde aquí.');
        }

        if ($this->oficioDocx->puedeGenerarDocx($solicitud) && ! SolicitudOficioFirmadoService::tieneFirmaDocente($solicitud)) {
            return redirect()
                ->route('solicitudes.firmar', $solicitud)
                ->with('error', 'Debes firmar el oficio antes de enviar a Secretaría.');
        }

        $user = auth()->user();
        $nuevoEstado = $this->workflow->estadoInicial($user->rol);
        if ($nuevoEstado === SolicitudEstado::EnBorrador) {
            abort(403);
        }

        $old = $solicitud->toArray();
        $solicitud->update(['estado' => $nuevoEstado]);
        $this->audit->log('UPDATE', $solicitud, $old);
        $this->appNotifications->onSolicitudCreada($solicitud->fresh(['creador']));

        return redirect()
            ->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud enviada a Secretaría.');
    }

    private function abortSiNoPuedeFirmar(Solicitud $solicitud): void
    {
        if ($solicitud->creado_por !== auth()->id()) {
            abort(403);
        }

        if (! in_array($solicitud->estado, [
            SolicitudEstado::EnBorrador,
            SolicitudEstado::EnRevisionSecretaria,
            SolicitudEstado::Aprobada,
        ], true)) {
            abort(403, 'Este trámite ya no admite firma del solicitante.');
        }
    }
}
