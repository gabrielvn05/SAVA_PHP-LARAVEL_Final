<?php

namespace App\Http\Controllers;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Services\OficioDocxService;
use App\Services\OficioPdfService;
use App\Services\OficioPreviewService;
use App\Support\OficioNombreArchivo;
use App\Support\OficioSelloFaltanteException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CertificadoController extends Controller
{
    public function __construct(
        private readonly OficioPreviewService $previewService,
        private readonly OficioDocxService $docxService,
        private readonly OficioPdfService $pdfService,
    ) {}

    public function preview(Request $request, Solicitud $solicitud): View|Response
    {
        $this->authorize('view', $solicitud);

        if ($request->query('html') !== '1' && $this->pdfService->usaVistaPdf($solicitud)) {
            $pdf = $this->pdfService->generar($solicitud);
            if ($pdf !== null) {
                $nombre = $this->nombreOficio($solicitud, 'pdf');

                return response($pdf, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="'.$nombre.'"',
                    'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                ]);
            }
        }

        if ($request->query('html') === '1' || ! $this->previewService->puedeUsarPlantillaDocx($solicitud)) {
            return response($this->previewService->renderHtmlRespaldo($solicitud))
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $detalle = $solicitud->detalle ?? [];

        return view('certificado.oficio-preview-shell', [
            'solicitud' => $solicitud,
            'docxUrl' => route('solicitudes.oficio-documento', $solicitud),
            'descargarUrl' => route('solicitudes.oficio-descargar', $solicitud),
            'htmlFallbackUrl' => route('solicitudes.preview-oficio', ['solicitud' => $solicitud, 'html' => 1]),
            'codigo' => $detalle['codigo_tramite'] ?? 'oficio',
        ]);
    }

    public function documento(Solicitud $solicitud): Response
    {
        $this->authorize('view', $solicitud);

        if (! $this->previewService->puedeUsarPlantillaDocx($solicitud)) {
            abort(404);
        }

        $binary = $this->generarDocxOAbortar($solicitud);
        $nombre = $this->nombreOficio($solicitud, 'docx');

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    public function descargar(Solicitud $solicitud): Response
    {
        $this->authorize('view', $solicitud);

        $pdf = $this->pdfService->generar($solicitud);
        if (is_string($pdf) && $pdf !== '') {
            $nombre = $this->nombreOficio($solicitud, 'pdf');

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
            ]);
        }

        if (! $this->previewService->puedeUsarPlantillaDocx($solicitud)) {
            abort(404);
        }

        $binary = $this->generarDocxOAbortar($solicitud);
        $nombre = $this->nombreOficio($solicitud, 'docx');

        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    private function generarDocxOAbortar(Solicitud $solicitud): string
    {
        try {
            return $this->docxService->generar($solicitud, $this->decanoFirmante($solicitud));
        } catch (OficioSelloFaltanteException $e) {
            abort(503, $e->getMessage());
        }
    }

    private function decanoFirmante(Solicitud $solicitud): ?\App\Models\User
    {
        if ($solicitud->estado !== SolicitudEstado::Aprobada) {
            return null;
        }

        $solicitud->loadMissing('firmante');

        return $solicitud->firmante;
    }

    private function nombreOficio(Solicitud $solicitud, string $extension): string
    {
        return OficioNombreArchivo::firmado($solicitud, $extension);
    }
}
