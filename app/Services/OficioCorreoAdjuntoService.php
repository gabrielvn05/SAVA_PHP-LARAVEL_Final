<?php

namespace App\Services;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Support\OficioNombreArchivo;
use App\Support\OficioSelloFaltanteException;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Log;

class OficioCorreoAdjuntoService
{
    public function __construct(
        private readonly OficioPdfService $pdf,
        private readonly OficioDocxService $docx,
    ) {}

    public function resolver(Solicitud $solicitud): OficioCorreoAdjuntoResultado
    {
        if ($solicitud->estado !== SolicitudEstado::Aprobada) {
            return new OficioCorreoAdjuntoResultado([], null);
        }

        $solicitud->loadMissing(['firmante', 'creador']);

        $pdf = $this->pdf->generar($solicitud);
        if (is_string($pdf) && $pdf !== '') {
            $nombre = OficioNombreArchivo::firmado($solicitud, 'pdf');

            return new OficioCorreoAdjuntoResultado([
                Attachment::fromData(fn (): string => $pdf, $nombre)->withMime('application/pdf'),
            ], 'pdf');
        }

        if (! $this->docx->puedeGenerarDocx($solicitud)) {
            $this->registrarFallo($solicitud, 'sin_plantilla_docx');

            return new OficioCorreoAdjuntoResultado([], null);
        }

        try {
            $docx = $this->docx->generar($solicitud, $solicitud->firmante);
        } catch (OficioSelloFaltanteException $e) {
            $this->registrarFallo($solicitud, 'sello_autorizado_faltante', $e->getMessage());

            return new OficioCorreoAdjuntoResultado([], null);
        } catch (\Throwable $e) {
            report($e);
            $this->registrarFallo($solicitud, 'docx_error', $e->getMessage());

            return new OficioCorreoAdjuntoResultado([], null);
        }

        if (! $this->pdf->puedeConvertir()) {
            $this->registrarFallo($solicitud, 'libreoffice_no_disponible');
        } else {
            $this->registrarFallo($solicitud, 'conversion_pdf_fallida');
        }

        $nombre = OficioNombreArchivo::firmado($solicitud, 'docx');

        return new OficioCorreoAdjuntoResultado([
            Attachment::fromData(fn (): string => $docx, $nombre)
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], 'docx');
    }

    private function registrarFallo(Solicitud $solicitud, string $motivo, ?string $detalle = null): void
    {
        Log::warning('Oficio no adjuntado como PDF en correo de aprobación', array_filter([
            'solicitud_id' => $solicitud->id,
            'motivo' => $motivo,
            'detalle' => $detalle,
            'libreoffice_path' => config('oficio.libreoffice_path'),
        ]));
    }
}
