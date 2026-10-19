<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Support\OficioNombreArchivo;
use App\Support\OficioSelloAutorizado;
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
        $pdf = $this->pdfService->generarParaFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            throw ValidationException::withMessages([
                'firma_imagen' => 'No se pudo preparar el PDF del oficio para firmar en el navegador.',
            ]);
        }

        $tmpPng = tempnam(sys_get_temp_dir(), 'firma-png-');
        if ($tmpPng === false) {
            throw ValidationException::withMessages(['firma_imagen' => 'No se pudo procesar la imagen de firma.']);
        }

        $pngPath = $tmpPng.'.png';
        @unlink($tmpPng);
        file_put_contents($pngPath, file_get_contents($imagenFirma->getRealPath()) ?: '');

        try {
            $pdfFirmado = $this->superponerSelloEnPdf($pdf, $pngPath, $pagina, $xPorcentaje, $yPorcentaje);
        } finally {
            @unlink($pngPath);
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
        ['titular' => $titular, 'serial' => $serial] = $this->leerCertificadoP12($p12, $contrasena, $solicitud);

        $pdf = $this->pdfService->generarParaFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo preparar el PDF del oficio.',
            ]);
        }

        $this->assertManipulacionPdfDisponible();
        $pdfFirmado = $this->superponerBloqueFirmaElectronicaEnPdf(
            $pdf,
            $solicitud,
            $titular,
            $serial,
            $pagina,
            $xPorcentaje,
            $yPorcentaje,
        );

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

        $tmpPng = tempnam(sys_get_temp_dir(), 'firma-png-');
        if ($tmpPng === false) {
            throw ValidationException::withMessages(['firma_imagen' => 'No se pudo procesar la imagen de firma.']);
        }

        $pngPath = $tmpPng.'.png';
        @unlink($tmpPng);
        file_put_contents($pngPath, file_get_contents($imagenFirma->getRealPath()) ?: '');

        $this->assertManipulacionPdfDisponible();
        $pdf = $this->pdfService->generarParaFirma($solicitud);
        if (! is_string($pdf) || $pdf === '') {
            @unlink($pngPath);
            throw ValidationException::withMessages([
                'firma_imagen' => 'No se pudo preparar el PDF del oficio.',
            ]);
        }

        try {
            $pdfFirmado = $this->superponerSelloEnPdf($pdf, $pngPath, $pagina, $xPorcentaje, $yPorcentaje);
        } finally {
            @unlink($pngPath);
        }

        if (! is_string($pdfFirmado) || $pdfFirmado === '') {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo generar el oficio firmado.',
            ]);
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

    private function superponerBloqueFirmaElectronicaEnPdf(
        string $pdfBytes,
        Solicitud $solicitud,
        string $titular,
        string $serial,
        int $paginaObjetivo,
        float $xPorcentaje,
        float $yPorcentaje,
    ): string {
        $pdf = $this->nuevoPdfFpdi();
        $reader = StreamReader::createByString($pdfBytes);
        $pageCount = $pdf->setSourceFile($reader);
        $paginaObjetivo = max(1, min($paginaObjetivo, $pageCount));

        $bloqueAncho = 72.0;
        $bloqueAlto = 22.0;
        $qrSize = 18.0;
        $aviso = (string) config('oficio.firma_electronica_aviso', 'Validar en SAVA ULEAM.');
        $qrPayload = $this->payloadQrFirmaElectronica($solicitud, $titular, $serial);
        [$nombreLinea1, $nombreLinea2] = $this->partirNombreTitularCertificado($titular);

        for ($page = 1; $page <= $pageCount; $page++) {
            $this->importarPaginaPdf($pdf, $page);
            if ($page !== $paginaObjetivo) {
                continue;
            }

            $pageW = $pdf->GetPageWidth();
            $pageH = $pdf->GetPageHeight();
            $x = max(4.0, ($xPorcentaje / 100) * $pageW - ($bloqueAncho / 2));
            $y = max(4.0, ($yPorcentaje / 100) * $pageH - ($bloqueAlto / 2));

            $estiloQr = [
                'border' => false,
                'padding' => 0,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
            ];
            $pdf->write2DBarcode($qrPayload, 'QRCODE,H', $x, $y + 0.5, $qrSize, $qrSize, $estiloQr, 'N');

            $textX = $x + $qrSize + 2.5;
            $textW = $bloqueAncho - $qrSize - 2.5;
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('courier', '', 6.5);
            $pdf->SetXY($textX, $y + 0.5);
            $pdf->Cell($textW, 3.2, $aviso, 0, 1, 'L');
            $pdf->SetX($textX);
            $pdf->Cell($textW, 3.2, 'Firmado electrónicamente por:', 0, 1, 'L');
            $pdf->SetFont('courier', 'B', 9);
            $pdf->SetX($textX);
            $pdf->Cell($textW, 4.6, mb_strtoupper($nombreLinea1), 0, 1, 'L');
            if ($nombreLinea2 !== '') {
                $pdf->SetX($textX);
                $pdf->Cell($textW, 4.6, mb_strtoupper($nombreLinea2), 0, 1, 'L');
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function partirNombreTitularCertificado(string $titular): array
    {
        $titular = trim(preg_replace('/\s+/u', ' ', $titular) ?? $titular);
        if ($titular === '') {
            return ['Titular del certificado', ''];
        }

        $partes = preg_split('/\s+/u', $titular) ?: [$titular];
        if (count($partes) < 3) {
            return [$titular, ''];
        }

        $mitad = (int) ceil(count($partes) / 2);

        return [
            implode(' ', array_slice($partes, 0, $mitad)),
            implode(' ', array_slice($partes, $mitad)),
        ];
    }

    private function payloadQrFirmaElectronica(Solicitud $solicitud, string $titular, string $serial): string
    {
        $base = (string) config('oficio.firma_electronica_qr_url', '');
        if ($base !== '') {
            return rtrim($base, '/').'/'.$solicitud->id;
        }

        $lineas = [
            'SAVA ULEAM',
            'Solicitud: '.$solicitud->id,
            'Titular: '.$titular,
        ];
        if ($serial !== '') {
            $lineas[] = 'Serie certificado: '.$serial;
        }
        $lineas[] = 'Fecha: '.now()->toIso8601String();

        return implode("\n", $lineas);
    }

    /**
     * Cubre el recuadro «Sello de APROBADO…» del PDF firmado por el solicitante.
     * Ese archivo se generó antes de la autorización, así que el sello no iba dentro.
     */
    public function superponerSelloAutorizado(string $pdfBytes): string
    {
        $this->assertManipulacionPdfDisponible();
        $imagen = OficioSelloAutorizado::assetPath();
        if (! is_readable($imagen)) {
            return $pdfBytes;
        }

        $pdf = $this->nuevoPdfFpdi();
        $reader = StreamReader::createByString($pdfBytes);
        $pageCount = $pdf->setSourceFile($reader);

        $recorte = $this->recorteSelloSinMargen($imagen);
        $ancho = 46.0;
        $alto = 46.0;
        $x = 142.5;
        $y = 34.0;

        try {
            for ($page = 1; $page <= $pageCount; $page++) {
                $this->importarPaginaPdf($pdf, $page);
                if ($page !== 1) {
                    continue;
                }

                $pageW = $pdf->GetPageWidth();
                $pageH = $pdf->GetPageHeight();
                $xPagina = max(4.0, min($x, $pageW - $ancho - 4));
                $yPagina = max(4.0, min($y, $pageH - $alto - 4));
                $pdf->Image($recorte, $xPagina, $yPagina, $ancho, $alto, 'JPEG');
            }
        } finally {
            if ($recorte !== $imagen) {
                @unlink($recorte);
            }
        }

        return $pdf->Output('', 'S');
    }

    private function recorteSelloSinMargen(string $imagen): string
    {
        if (! function_exists('imagecreatefromjpeg')) {
            return $imagen;
        }

        $origen = @imagecreatefromjpeg($imagen);
        if ($origen === false) {
            return $imagen;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $minX = $ancho;
        $minY = $alto;
        $maxX = 0;
        $maxY = 0;

        for ($y = 0; $y < $alto; $y += 2) {
            for ($x = 0; $x < $ancho; $x += 2) {
                $color = imagecolorat($origen, $x, $y);
                $rojo = ($color >> 16) & 0xFF;
                $verde = ($color >> 8) & 0xFF;
                $azul = $color & 0xFF;
                if ($rojo > 245 && $verde > 245 && $azul > 245) {
                    continue;
                }
                $minX = min($minX, $x);
                $minY = min($minY, $y);
                $maxX = max($maxX, $x);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX <= $minX || $maxY <= $minY) {
            imagedestroy($origen);

            return $imagen;
        }

        $recorte = imagecrop($origen, [
            'x' => $minX,
            'y' => $minY,
            'width' => $maxX - $minX + 2,
            'height' => $maxY - $minY + 2,
        ]);
        imagedestroy($origen);
        if ($recorte === false) {
            return $imagen;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'sello-').'.jpg';
        imagejpeg($recorte, $tmp, 92);
        imagedestroy($recorte);

        return $tmp;
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
