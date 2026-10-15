<?php

namespace App\Http\Controllers;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Services\AppNotificationService;
use App\Services\AuditService;
use App\Services\OficioDocxService;
use App\Services\OficioPdfService;
use App\Services\SolicitudOficioFirmadoService;
use App\Services\SolicitudWorkflowService;
use App\Support\OficioNombreArchivo;
use App\Support\SolicitudAdjuntos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SolicitudFirmaDocenteController extends Controller
{
    public function __construct(
        private readonly SolicitudOficioFirmadoService $oficioFirmado,
        private readonly OficioDocxService $oficioDocx,
        private readonly OficioPdfService $oficioPdf,
        private readonly SolicitudWorkflowService $workflow,
        private readonly AuditService $audit,
        private readonly AppNotificationService $appNotifications,
    ) {}

    public function show(Request $request, Solicitud $solicitud): View
    {
        $this->authorize('update', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

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
        $this->authorize('update', $solicitud);
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

    public function firmarP12Documento(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('update', $solicitud);
        $this->abortSiNoPuedeFirmar($solicitud);

        $request->validate([
            'p12' => ['required', 'file', 'max:5120'],
            'p12_password' => ['required', 'string', 'max:255'],
            'firma_imagen' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'firma_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'firma_x' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'firma_y' => ['nullable', 'numeric', 'min:0', 'max:100'],
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

            return $this->redirectTrasGuardarFirma($solicitud, $firmado, 'Oficio firmado con certificado .p12. Revisa el documento y envía a Secretaría.');
        }

        $firmado = $this->oficioFirmado->firmarConP12(
            $solicitud,
            $p12,
            (string) $request->input('p12_password'),
            $anexos,
        );

        return $this->redirectTrasGuardarFirma($solicitud, $firmado, 'Oficio firmado electrónicamente. Revisa el documento y envía a Secretaría.');
    }

    public function subirManual(Request $request, Solicitud $solicitud): RedirectResponse
    {
        $this->authorize('update', $solicitud);
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

        return $this->redirectTrasGuardarFirma($solicitud, $firmado, 'Oficio firmado subido correctamente. Revisa el archivo y envía a Secretaría.');
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

        return redirect()
            ->route('solicitudes.firmar', $solicitud)
            ->with('success', $mensaje);
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

        if (! in_array($solicitud->estado, [SolicitudEstado::EnBorrador, SolicitudEstado::EnRevisionSecretaria], true)) {
            abort(403, 'Este trámite ya no admite firma del solicitante.');
        }
    }
}
