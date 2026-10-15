<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Support\OficioNombreArchivo;
use App\Support\SolicitudAdjuntos;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

class SolicitudOficioFirmadoService
{
    public const ROL_ANEXO = 'oficio_firmado_docente';

    public function __construct(private readonly OficioPdfService $pdfService) {}

    private function assertManipulacionPdfDisponible(): void
    {
        if (! class_exists(Fpdi::class)) {
            throw ValidationException::withMessages([
                'p12' => 'En el servidor faltan las librerías PDF (FPDI/TCPDF). Ejecuta «composer install --no-dev» en el proyecto y vuelve a intentar.',
            ]);
        }
    }

    /**
     * @param  list<array{path: string, nombre: string, rol?: string}>  $anexos
     * @return array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}
     */
    public function aplicarSubidaManual(Solicitud $solicitud, UploadedFile $file, array $anexos): array
    {
        $this->validarArchivoFirmado($file);

        $path = SolicitudAdjuntos::guardar($file);
        $nombre = $file->getClientOriginalName() ?: OficioNombreArchivo::base($solicitud).'-firmado-docente.pdf';

        return $this->insertarOficioFirmado($anexos, $path, $nombre, [
            'tipo' => 'manual',
            'fecha' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  list<array{path: string, nombre: string, rol?: string}>  $anexos
     * @return array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}
     */
    /**
     * @param  list<array{path: string, nombre: string, rol?: string}>  $anexos
     * @return array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}
     */
    public function firmarConImagenNavegador(
        Solicitud $solicitud,
        UploadedFile $imagenFirma,
        int $pagina,
        float $xPorcentaje,
        float $yPorcentaje,
        array $anexos,
    ): array {
        $this->assertManipulacionPdfDisponible();
        $pdf = $this->pdfService->pdfParaSuperponerFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            throw ValidationException::withMessages([
                'firma_imagen' => 'No se pudo preparar el PDF del oficio para firmar en el navegador.',
            ]);
        }

        $tmpPng = tempnam(sys_get_temp_dir(), 'firma-png-');
        if ($tmpPng === false) {
            throw ValidationException::withMessages(['firma_imagen' => 'No se pudo procesar la imagen de firma.']);
        }

        file_put_contents($tmpPng, file_get_contents($imagenFirma->getRealPath()) ?: '');

        try {
            $pdfFirmado = $this->superponerSelloEnPdf($pdf, $tmpPng, $pagina, $xPorcentaje, $yPorcentaje);
        } finally {
            @unlink($tmpPng);
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-navegador.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, [
            'tipo' => 'navegador',
            'pagina' => (string) $pagina,
            'fecha' => now()->toIso8601String(),
        ]);
    }

    public static function tieneFirmaDocente(Solicitud $solicitud): bool
    {
        return self::oficioFirmadoEnAnexos($solicitud) !== null;
    }

    /**
     * @param  list<array{path: string, nombre: string, rol?: string}>  $anexos
     * @return array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}
     */
    /**
     * @param  list<array{path: string, nombre: string, rol?: string}>  $anexos
     * @return array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}
     */
    public function firmarConP12EnPosicion(
        Solicitud $solicitud,
        UploadedFile $p12,
        string $contrasena,
        int $pagina,
        float $xPorcentaje,
        float $yPorcentaje,
        array $anexos,
    ): array {
        $this->assertManipulacionPdfDisponible();
        ['titular' => $titular, 'serial' => $serial] = $this->leerCertificadoP12($p12, $contrasena, $solicitud);

        $pdf = $this->pdfService->pdfParaSuperponerFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo preparar el PDF del oficio.',
            ]);
        }

        if (function_exists('imagecreatetruecolor')) {
            $selloPng = $this->crearPngSelloElectronico($titular);
            try {
                $pdfFirmado = $this->superponerSelloEnPdf($pdf, $selloPng, $pagina, $xPorcentaje, $yPorcentaje);
            } finally {
                @unlink($selloPng);
            }
        } else {
            $pdfFirmado = $this->superponerTextoEnPdf($pdf, $titular, $pagina, $xPorcentaje, $yPorcentaje);
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-p12.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, [
            'tipo' => 'p12_documento',
            'titular' => $titular,
            'serial' => $serial,
            'pagina' => (string) $pagina,
            'fecha' => now()->toIso8601String(),
        ]);
    }

    public function firmarConP12EImagenEnDocumento(
        Solicitud $solicitud,
        UploadedFile $p12,
        string $contrasena,
        UploadedFile $imagenFirma,
        int $pagina,
        float $xPorcentaje,
        float $yPorcentaje,
        array $anexos,
    ): array {
        ['titular' => $titular, 'serial' => $serial] = $this->leerCertificadoP12($p12, $contrasena, $solicitud);

        $pdf = $this->pdfService->pdfParaSuperponerFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo preparar el PDF del oficio.',
            ]);
        }

        $tmpPng = tempnam(sys_get_temp_dir(), 'firma-png-');
        if ($tmpPng === false) {
            throw ValidationException::withMessages(['firma_imagen' => 'No se pudo procesar la imagen de firma.']);
        }

        file_put_contents($tmpPng, file_get_contents($imagenFirma->getRealPath()) ?: '');

        try {
            $pdfFirmado = $this->superponerSelloEnPdf($pdf, $tmpPng, $pagina, $xPorcentaje, $yPorcentaje);
        } finally {
            @unlink($tmpPng);
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-p12.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, [
            'tipo' => 'p12_documento',
            'titular' => $titular,
            'serial' => $serial,
            'pagina' => (string) $pagina,
            'fecha' => now()->toIso8601String(),
        ]);
    }

    public function firmarConP12(Solicitud $solicitud, UploadedFile $p12, string $contrasena, array $anexos): array
    {
        ['titular' => $titular, 'serial' => $serial] = $this->leerCertificadoP12($p12, $contrasena, $solicitud);

        $pdf = $this->pdfService->pdfBaseParaFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo generar el PDF del oficio en el servidor. Descarga Word o PDF, fírmalo externamente y súbelo.',
            ]);
        }

        $pdfFirmado = $this->agregarHojaFirmaElectronica($pdf, $titular, now()->locale('es')->translatedFormat('d \d\e F \d\e Y H:i'), $serial);
        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-electronico.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, [
            'tipo' => 'p12',
            'titular' => $titular,
            'serial' => $serial,
            'fecha' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  list<array{path: string, nombre: string, rol?: string}>  $anexos
     * @return array{anexos: list<array{path: string, nombre: string, rol?: string}>, firma_docente: array<string, string>}
     */
    private function insertarOficioFirmado(array $anexos, string $path, string $nombre, array $meta): array
    {
        foreach ($anexos as $anexo) {
            if (($anexo['rol'] ?? null) === self::ROL_ANEXO && ! empty($anexo['path'])) {
                SolicitudAdjuntos::borrar((string) $anexo['path']);
            }
        }

        $anexos = array_values(array_filter(
            $anexos,
            static fn (array $anexo): bool => ($anexo['rol'] ?? null) !== self::ROL_ANEXO,
        ));

        $anexos[] = [
            'path' => $path,
            'nombre' => $nombre,
            'rol' => self::ROL_ANEXO,
        ];

        return [
            'anexos' => $anexos,
            'firma_docente' => $meta,
        ];
    }

    /**
     * @return array{titular: string, serial: string}
     */
    private function crearPngSelloElectronico(string $titular): string
    {
        $ancho = 240;
        $alto = 56;
        $imagen = imagecreatetruecolor($ancho, $alto);
        if ($imagen === false) {
            throw ValidationException::withMessages(['p12' => 'No se pudo generar el sello de firma en el servidor.']);
        }

        $blanco = imagecolorallocate($imagen, 255, 255, 255);
        $azul = imagecolorallocate($imagen, 12, 61, 122);
        imagefill($imagen, 0, 0, $blanco);
        imagerectangle($imagen, 0, 0, $ancho - 1, $alto - 1, $azul);
        imagestring($imagen, 3, 10, 10, 'Firma electronica', $azul);
        $linea = mb_strlen($titular) > 36 ? mb_substr($titular, 0, 33).'...' : $titular;
        imagestring($imagen, 2, 10, 30, $linea, $azul);

        $path = tempnam(sys_get_temp_dir(), 'sello-p12-');
        if ($path === false) {
            imagedestroy($imagen);
            throw ValidationException::withMessages(['p12' => 'No se pudo generar el sello de firma.']);
        }

        $pngPath = $path.'.png';
        @unlink($path);
        imagepng($imagen, $pngPath);
        imagedestroy($imagen);

        return $pngPath;
    }

    /**
     * @return array{titular: string, serial: string}
     */
    private function leerCertificadoP12(UploadedFile $p12, string $contrasena, Solicitud $solicitud): array
    {
        $contenidoP12 = file_get_contents($p12->getRealPath()) ?: '';
        $certificados = [];
        if ($contenidoP12 === '' || ! openssl_pkcs12_read($contenidoP12, $certificados, $contrasena)) {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo leer el certificado. Verifica el archivo .p12 y la contraseña.',
            ]);
        }

        unset($contenidoP12, $certificados['pkey']);

        $info = openssl_x509_parse($certificados['cert'] ?? '') ?: [];
        $titular = (string) ($info['subject']['CN'] ?? $solicitud->creador?->nombreCompleto() ?? 'Titular del certificado');
        $serial = isset($info['serialNumber']) ? (string) $info['serialNumber'] : '';

        return ['titular' => $titular, 'serial' => $serial];
    }

    private function validarArchivoFirmado(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
            throw ValidationException::withMessages([
                'oficio_firmado_docente' => 'El oficio firmado debe ser PDF, PNG o JPG.',
            ]);
        }
    }

    private function agregarHojaFirmaElectronica(string $pdfBytes, string $titular, string $fecha, string $serial): string
    {
        $pdf = new Fpdi;
        $pdf->SetCreator('SAVA ULEAM');
        $pdf->SetAuthor($titular);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $reader = StreamReader::createByString($pdfBytes);
        $pageCount = $pdf->setSourceFile($reader);

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }

        $pdf->AddPage();
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 10, 'Constancia de firma electrónica del solicitante', 0, 1);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Ln(4);
        $pdf->MultiCell(0, 6, "El documento anterior fue firmado electrónicamente mediante certificado digital (.p12) en SAVA.\n\nTitular del certificado: {$titular}\nFecha y hora: {$fecha}".($serial !== '' ? "\nN.º de serie: {$serial}" : '')."\n\nEl archivo .p12 no se almacena en el sistema; solo se usó en el momento de la firma.", 0, 'L');

        return $pdf->Output('', 'S');
    }

    private function superponerSelloEnPdf(
        string $pdfBytes,
        string $pngPath,
        int $paginaObjetivo,
        float $xPorcentaje,
        float $yPorcentaje,
    ): string {
        $pdf = $this->nuevoPdfFpdi();
        $reader = StreamReader::createByString($pdfBytes);
        $pageCount = $pdf->setSourceFile($reader);
        $paginaObjetivo = max(1, min($paginaObjetivo, $pageCount));

        for ($page = 1; $page <= $pageCount; $page++) {
            $this->importarPaginaPdf($pdf, $page);
            if ($page === $paginaObjetivo) {
                $anchoFirma = 45.0;
                $altoFirma = 16.0;
                $pageW = $pdf->GetPageWidth();
                $pageH = $pdf->GetPageHeight();
                $x = ($xPorcentaje / 100) * $pageW - ($anchoFirma / 2);
                $y = ($yPorcentaje / 100) * $pageH - ($altoFirma / 2);
                $pdf->Image($pngPath, max(4, $x), max(4, $y), $anchoFirma, $altoFirma, 'PNG');
            }
        }

        return $pdf->Output('', 'S');
    }

    private function superponerTextoEnPdf(
        string $pdfBytes,
        string $titular,
        int $paginaObjetivo,
        float $xPorcentaje,
        float $yPorcentaje,
    ): string {
        $pdf = $this->nuevoPdfFpdi();
        $reader = StreamReader::createByString($pdfBytes);
        $pageCount = $pdf->setSourceFile($reader);
        $paginaObjetivo = max(1, min($paginaObjetivo, $pageCount));

        for ($page = 1; $page <= $pageCount; $page++) {
            $this->importarPaginaPdf($pdf, $page);

            if ($page === $paginaObjetivo) {
                $pageW = $pdf->GetPageWidth();
                $pageH = $pdf->GetPageHeight();
                $x = max(4.0, ($xPorcentaje / 100) * $pageW - 20);
                $y = max(4.0, ($yPorcentaje / 100) * $pageH - 8);
                $pdf->SetFont('helvetica', 'B', 9);
                $pdf->SetXY($x, $y);
                $pdf->Cell(80, 5, 'Firma electronica', 0, 1);
                $pdf->SetFont('helvetica', '', 8);
                $pdf->SetX($x);
                $pdf->MultiCell(80, 4, mb_substr($titular, 0, 48), 0, 'L');
            }
        }

        return $pdf->Output('', 'S');
    }

    private function nuevoPdfFpdi(): Fpdi
    {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        return $pdf;
    }

    private function importarPaginaPdf(Fpdi $pdf, int $page): void
    {
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);
        $orientation = $size['orientation'] ?? ((($size['width'] ?? 0) > ($size['height'] ?? 0)) ? 'L' : 'P');
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($template, 0, 0, $size['width'], $size['height'], true);
    }

    public static function oficioFirmadoEnAnexos(Solicitud $solicitud): ?array
    {
        foreach (SolicitudAdjuntos::anexosActuales($solicitud) as $anexo) {
            if (($anexo['rol'] ?? null) === self::ROL_ANEXO) {
                return $anexo;
            }
        }

        return null;
    }
}
