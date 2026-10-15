<?php

namespace App\Services;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class OficioPdfService
{
    public function __construct(private readonly OficioDocxService $docx) {}

    public function puedeConvertir(): bool
    {
        $path = config('oficio.libreoffice_path');
        if (! is_string($path) || $path === '') {
            return false;
        }

        if ($path !== 'soffice' && ! is_file($path)) {
            return false;
        }

        return true;
    }

    public function usaVistaPdf(Solicitud $solicitud): bool
    {
        return $this->docx->puedeGenerarDocx($solicitud) && $this->puedeConvertir();
    }

    /** El visor iframe solo debe esperar PDF si la conversión ya funciona en este servidor. */
    public function puedeMostrarPdfEnVisor(Solicitud $solicitud): bool
    {
        if (! $this->usaVistaPdf($solicitud)) {
            return false;
        }

        return $this->generar($solicitud) !== null;
    }

    public function generarParaFirma(Solicitud $solicitud): ?string
    {
        $desdeCache = $this->leerCachePdf($solicitud);
        if ($desdeCache !== null) {
            return $desdeCache;
        }

        $htmlPdf = $this->generarDesdeHtmlRespaldo($solicitud);
        if (is_string($htmlPdf) && $htmlPdf !== '') {
            $this->escribirCachePdf($solicitud, $htmlPdf);

            return $htmlPdf;
        }

        $pdf = $this->generar($solicitud);
        if (is_string($pdf) && $pdf !== '') {
            return $pdf;
        }

        return null;
    }

    /** PDF base para superponer firma: reutiliza el mismo archivo que la vista previa. */
    public function pdfBaseParaFirma(Solicitud $solicitud): ?string
    {
        $desdeCache = $this->leerCachePdf($solicitud);
        if ($desdeCache !== null) {
            return $desdeCache;
        }

        return $this->generarParaFirma($solicitud);
    }

    private function leerCachePdf(Solicitud $solicitud): ?string
    {
        $path = $this->cachePath($solicitud);
        if (! is_file($path)) {
            return null;
        }

        $bytes = file_get_contents($path);

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }

    private function escribirCachePdf(Solicitud $solicitud, string $pdf): void
    {
        $path = $this->cachePath($solicitud);
        File::ensureDirectoryExists(dirname($path));
        file_put_contents($path, $pdf);
    }

    public function generar(Solicitud $solicitud): ?string
    {
        if (! $this->docx->puedeGenerarDocx($solicitud)) {
            return null;
        }

        $cachePath = $this->cachePath($solicitud);
        if ($this->cacheValido($cachePath, $solicitud)) {
            return file_get_contents($cachePath) ?: null;
        }

        try {
            $docx = $this->docx->generar($solicitud, $this->decanoParaOficio($solicitud));
        } catch (\App\Support\OficioSelloFaltanteException $e) {
            report($e);

            return null;
        }
        $pdf = $this->convertirDocx($docx);
        if ($pdf === null) {
            return null;
        }

        File::ensureDirectoryExists(dirname($cachePath));
        file_put_contents($cachePath, $pdf);

        return $pdf;
    }

    public function invalidarCache(Solicitud $solicitud): void
    {
        foreach (glob(storage_path('app/oficios/pdf/'.$solicitud->id.'.*.pdf')) ?: [] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    public const PLANTILLA_VERSION = '2026-10-13a';

    private function cachePath(Solicitud $solicitud): string
    {
        $estado = $solicitud->estado === SolicitudEstado::Aprobada ? 'firmado' : 'pre';

        return storage_path('app/oficios/pdf/'.$solicitud->id.'.'.$estado.'.'.self::PLANTILLA_VERSION.'.pdf');
    }

    private function decanoParaOficio(Solicitud $solicitud): ?User
    {
        if ($solicitud->estado !== SolicitudEstado::Aprobada) {
            return null;
        }

        $solicitud->loadMissing('firmante');

        return $solicitud->firmante;
    }

    private function cacheValido(string $path, Solicitud $solicitud): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $minutes = max(1, (int) config('oficio.pdf_cache_minutes', 120));
        if (time() - filemtime($path) > $minutes * 60) {
            return false;
        }

        $updatedAt = $solicitud->updated_at?->getTimestamp() ?? 0;
        if (filemtime($path) < $updatedAt) {
            return false;
        }

        return ! $this->plantillaMasRecienteQue($path, $solicitud);
    }

    private function plantillaMasRecienteQue(string $pdfPath, Solicitud $solicitud): bool
    {
        $plantilla = app(OficioDatosService::class)->plantillaPath($solicitud->tipo);
        if ($plantilla === null || ! is_file($plantilla)) {
            return false;
        }

        return filemtime($plantilla) > filemtime($pdfPath);
    }

    private function convertirDocx(string $docxBinary): ?string
    {
        if (! $this->puedeConvertir()) {
            return null;
        }

        $workDir = storage_path('app/temp/oficio-'.Str::uuid());
        File::ensureDirectoryExists($workDir);

        $inputPath = $workDir.DIRECTORY_SEPARATOR.'oficio.docx';
        file_put_contents($inputPath, $docxBinary);

        $soffice = (string) config('oficio.libreoffice_path');
        $process = new Process([
            $soffice,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '--convert-to',
            'pdf',
            '--outdir',
            $workDir,
            $inputPath,
        ]);
        $timeout = max(15, (int) config('oficio.libreoffice_timeout_seconds', 45));
        $process->setTimeout($timeout);
        $process->run();

        $pdfPath = $workDir.DIRECTORY_SEPARATOR.'oficio.pdf';
        $pdf = is_file($pdfPath) ? (file_get_contents($pdfPath) ?: null) : null;

        File::deleteDirectory($workDir);

        return $pdf;
    }

    private function generarDesdeHtmlRespaldo(Solicitud $solicitud): ?string
    {
        try {
            $html = app(OficioPreviewService::class)->renderHtmlRespaldo($solicitud);
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetMargins(14, 16, 14);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');

            return $pdf->Output('', 'S');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
