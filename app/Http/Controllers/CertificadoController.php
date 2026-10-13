<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Services\OficioDocxService;
use App\Services\OficioPdfService;
use App\Services\OficioPreviewService;
use App\Support\ViteManifest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class CertificadoController extends Controller
{
    public function __construct(
        private readonly OficioPreviewService $previewService,
        private readonly OficioDocxService $docxService,
        private readonly OficioPdfService $pdfService,
    ) {}

    public function preview(Solicitud $solicitud): View|Response
    {
        $this->authorize('view', $solicitud);

        if ($this->pdfService->usaVistaPdf($solicitud)) {
            $pdf = $this->pdfService->generar($solicitud);
            if ($pdf !== null) {
                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="oficio.pdf"',
                    'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                ]);
            }
        }

        if ($this->previewService->puedeUsarPlantillaDocx($solicitud)) {
            $detalle = $solicitud->detalle ?? [];
            $descargarUrl = Route::has('solicitudes.oficio-descargar')
                ? route('solicitudes.oficio-descargar', $solicitud)
                : route('solicitudes.oficio-documento', $solicitud);

            return view('certificado.oficio-preview-shell', [
                'solicitud' => $solicitud,
                'docxUrl' => route('solicitudes.oficio-documento', $solicitud),
                'descargarUrl' => $descargarUrl,
                'codigo' => $detalle['codigo_tramite'] ?? 'oficio',
                'avisoAproximado' => true,
                'pdfNoDisponible' => ! $this->pdfService->puedeConvertir(),
                'oficioPreviewScriptUrl' => ViteManifest::moduleUrl('resources/js/oficio-preview.js'),
            ]);
        }

        return response($this->previewService->renderHtmlRespaldo($solicitud))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function documento(Solicitud $solicitud): Response
    {
        $this->authorize('view', $solicitud);

        if (! $this->previewService->puedeUsarPlantillaDocx($solicitud)) {
            abort(404);
        }

        $binary = $this->docxService->generar($solicitud);
        $detalle = $solicitud->detalle ?? [];
        $nombre = ($detalle['codigo_tramite'] ?? 'oficio').'.docx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function descargar(Solicitud $solicitud): Response
    {
        $this->authorize('view', $solicitud);

        if (! $this->previewService->puedeUsarPlantillaDocx($solicitud)) {
            abort(404);
        }

        $binary = $this->docxService->generar($solicitud);
        $detalle = $solicitud->detalle ?? [];
        $nombre = ($detalle['codigo_tramite'] ?? 'oficio').'.docx';

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }
}
