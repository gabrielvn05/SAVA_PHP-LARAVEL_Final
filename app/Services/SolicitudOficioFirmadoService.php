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
        $pdf = $this->pdfService->pdfBaseParaFirma($solicitud);
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
            $pdfFirmado = $this->superponerImagenEnPdf($pdf, $tmpPng, $pagina, $xPorcentaje, $yPorcentaje);
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

    public function firmarConP12(Solicitud $solicitud, UploadedFile $p12, string $contrasena, array $anexos): array
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

    private function superponerImagenEnPdf(
        string $pdfBytes,
        string $pngPath,
        int $paginaObjetivo,
        float $xPorcentaje,
        float $yPorcentaje,
    ): string {
        $pdf = new Fpdi;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        $reader = StreamReader::createByString($pdfBytes);
        $pageCount = $pdf->setSourceFile($reader);
        $paginaObjetivo = max(1, min($paginaObjetivo, $pageCount));

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $orientation = ($size['width'] ?? 0) > ($size['height'] ?? 0) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

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
