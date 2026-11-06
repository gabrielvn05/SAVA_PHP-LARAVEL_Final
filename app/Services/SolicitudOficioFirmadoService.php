<?php

namespace App\Services;

use App\Enums\SolicitudEstado;
use App\Models\Solicitud;
use App\Support\OficioNombreArchivo;
use App\Support\OficioSelloAutorizado;
use App\Support\PdfFirmaDigital;
use App\Support\PdfFirmador;
use App\Support\PdfSelloIncremental;
use App\Support\SolicitudAdjuntos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfParser\Type\PdfArray;
use setasign\Fpdi\PdfParser\Type\PdfDictionary;
use setasign\Fpdi\PdfParser\Type\PdfName;
use setasign\Fpdi\PdfParser\Type\PdfNumeric;
use setasign\Fpdi\PdfParser\Type\PdfStream;
use setasign\Fpdi\PdfParser\Type\PdfType;
use setasign\Fpdi\PdfReader\PdfReader;
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
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        if (! in_array($extension, ['pdf', 'png', 'jpg', 'jpeg'], true)) {
            $extension = 'pdf';
        }
        $nombre = OficioNombreArchivo::base($solicitud).'-firmado.'.$extension;

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

    public static function firmaIncluyeSello(Solicitud $solicitud): bool
    {
        $firma = $solicitud->detalle['firma_docente'] ?? null;

        return is_array($firma) && (string) ($firma['incluye_sello'] ?? '') === '1';
    }

    /**
     * El PDF firmado por el solicitante, sin reescribirlo al aprobar.
     * El sello ya quedó dentro de esa firma.
     */
    public function pdfAprobadoConFirmaSolicitante(Solicitud $solicitud): ?string
    {
        if ($solicitud->estado !== SolicitudEstado::Aprobada) {
            return null;
        }

        $anexo = self::oficioFirmadoEnAnexos($solicitud);
        if ($anexo === null) {
            return null;
        }

        $nombre = strtolower((string) ($anexo['nombre'] ?? ''));
        $path = (string) ($anexo['path'] ?? '');
        if ($path === '' || ! str_ends_with($nombre, '.pdf')) {
            return null;
        }

        $disk = SolicitudAdjuntos::diskConArchivo($path);
        if ($disk === null) {
            return null;
        }

        $bytes = Storage::disk($disk)->get($path);
        if (! is_string($bytes) || ! str_starts_with($bytes, '%PDF')) {
            return null;
        }

        try {
            $sellado = $this->aplicarSelloDeAprobacion($solicitud, $bytes);
        } catch (\Throwable $e) {
            report($e);

            return $bytes;
        }

        return $sellado !== '' ? $sellado : $bytes;
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
        $material = $this->leerCertificadoP12($p12, $contrasena, $solicitud);
        $conSello = false;
        $imagenSello = null;

        try {
            $pdf = $this->pdfService->generarParaFirma($solicitud, ! $conSello);
            if (! is_string($pdf) || $pdf === '') {
                throw ValidationException::withMessages([
                    'p12' => 'No se pudo preparar el PDF del oficio.',
                ]);
            }

            $this->assertManipulacionPdfDisponible();
            $pdfFirmado = $this->conFirmaDigital(
                fn (): string => $this->superponerBloqueFirmaElectronicaEnPdf(
                    $pdf,
                    $solicitud,
                    $material,
                    $pagina,
                    $xPorcentaje,
                    $yPorcentaje,
                    $imagenSello,
                ),
            );
        } finally {
            $this->descartarClave($material);
            $this->descartarRecorteSello($imagenSello);
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-p12.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, $this->metaFirmaP12($material, $pagina, $conSello, 'p12_documento'));
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
        $material = $this->leerCertificadoP12($p12, $contrasena, $solicitud);
        $pngPath = null;
        $conSello = false;
        $imagenSello = null;

        try {
            $tmpPng = tempnam(sys_get_temp_dir(), 'firma-png-');
            if ($tmpPng === false) {
                throw ValidationException::withMessages(['firma_imagen' => 'No se pudo procesar la imagen de firma.']);
            }

            $pngPath = $tmpPng.'.png';
            @unlink($tmpPng);
            file_put_contents($pngPath, file_get_contents($imagenFirma->getRealPath()) ?: '');

            $this->assertManipulacionPdfDisponible();
            $pdf = $this->pdfService->generarParaFirma($solicitud, ! $conSello);
            if (! is_string($pdf) || $pdf === '') {
                throw ValidationException::withMessages([
                    'firma_imagen' => 'No se pudo preparar el PDF del oficio.',
                ]);
            }

            $pdfFirmado = $this->conFirmaDigital(
                fn (): string => $this->superponerSelloYFirmarEnPdf(
                    $pdf,
                    $pngPath,
                    $material,
                    $pagina,
                    $xPorcentaje,
                    $yPorcentaje,
                    $imagenSello,
                ),
            );
        } finally {
            if (is_string($pngPath)) {
                @unlink($pngPath);
            }
            $this->descartarClave($material);
            $this->descartarRecorteSello($imagenSello);
        }

        if (! is_string($pdfFirmado) || $pdfFirmado === '') {
            throw ValidationException::withMessages([
                'p12' => 'No se pudo generar el oficio firmado.',
            ]);
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-p12.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, $this->metaFirmaP12($material, $pagina, $conSello, 'p12_documento'));
    }

    /**
     * Guarda un PDF que ya trae la firma de FirmaEC, sin volver a abrirlo.
     *
     * @param  array<string, string>  $meta
     */
    public function guardarPdfFirmadoExterno(Solicitud $solicitud, string $pdf, string $nombre, array $meta): void
    {
        if (! str_starts_with($pdf, '%PDF')) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo devuelto no es un PDF.',
            ]);
        }

        $firmado = $this->insertarOficioFirmado(
            SolicitudAdjuntos::anexosActuales($solicitud),
            SolicitudAdjuntos::guardarContenido($pdf, 'pdf'),
            $nombre,
            $meta,
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
    }

    public function firmarConP12(Solicitud $solicitud, UploadedFile $p12, string $contrasena, array $anexos): array
    {
        $material = $this->leerCertificadoP12($p12, $contrasena, $solicitud);
        $conSello = false;
        $imagenSello = null;

        try {
            $pdf = $this->pdfService->generarParaFirma($solicitud, ! $conSello);
            if (! is_string($pdf) || $pdf === '') {
                throw ValidationException::withMessages([
                    'p12' => 'No se pudo generar el PDF del oficio en el servidor. Descarga Word o PDF, fírmalo externamente y súbelo.',
                ]);
            }

            $pdfFirmado = $this->conFirmaDigital(
                fn (): string => $this->agregarHojaFirmaElectronica(
                    $pdf,
                    $material,
                    now()->locale('es')->translatedFormat('d \d\e F \d\e Y H:i'),
                    $imagenSello,
                ),
            );
        } finally {
            $this->descartarClave($material);
            $this->descartarRecorteSello($imagenSello);
        }

        $nombre = OficioNombreArchivo::base($solicitud).'-firmado-electronico.pdf';
        $path = SolicitudAdjuntos::guardarContenido($pdfFirmado, 'pdf');

        return $this->insertarOficioFirmado($anexos, $path, $nombre, $this->metaFirmaP12($material, null, $conSello, 'p12'));
    }

    /**
     * @param  array{titular: string, serial: string}  $material
     * @return array<string, string>
     */
    private function metaFirmaP12(array $material, ?int $pagina, bool $conSello, string $tipo): array
    {
        $meta = [
            'tipo' => $tipo,
            'formato' => 'pades',
            'titular' => $material['titular'],
            'serial' => $material['serial'],
            'fecha' => now()->toIso8601String(),
        ];
        if ($pagina !== null) {
            $meta['pagina'] = (string) $pagina;
        }
        if ($conSello) {
            $meta['incluye_sello'] = '1';
        }

        return $meta;
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
     * La clave privada solo vive en esta petición. No se guarda en la solicitud.
     *
     * @return array{titular: string, serial: string, cert: string, pkey: string, extracerts: string}
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

        $cert = (string) ($certificados['cert'] ?? '');
        $pkey = (string) ($certificados['pkey'] ?? '');
        $extra = '';
        if (isset($certificados['extracerts']) && is_array($certificados['extracerts'])) {
            $extra = implode("\n", array_map('strval', $certificados['extracerts']));
        }

        unset($contenidoP12, $certificados);

        if ($cert === '' || $pkey === '') {
            throw ValidationException::withMessages([
                'p12' => 'El archivo .p12 no incluye certificado y clave privada.',
            ]);
        }

        $info = openssl_x509_parse($cert) ?: [];
        $titular = (string) ($info['subject']['CN'] ?? $solicitud->creador?->nombreCompleto() ?? 'Titular del certificado');
        $serial = isset($info['serialNumber']) ? (string) $info['serialNumber'] : '';

        return [
            'titular' => $titular,
            'serial' => $serial,
            'cert' => $cert,
            'pkey' => $pkey,
            'extracerts' => $extra,
        ];
    }

    /**
     * @param  array{titular: string, serial: string, cert: string, pkey: string, extracerts: string}  $material
     */
    private function descartarClave(array &$material): void
    {
        $material['pkey'] = '';
        $material['cert'] = '';
        $material['extracerts'] = '';
    }

    /**
     * @param  callable(): string  $generar
     */
    private function conFirmaDigital(callable $generar): string
    {
        try {
            return $generar();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'p12' => 'No se pudo incrustar la firma digital en el PDF. Verifica el certificado .p12 e inténtalo de nuevo.',
            ]);
        }
    }

    /**
     * @param  array{titular: string, serial: string, cert: string, pkey: string, extracerts: string}  $material
     * @return array{Name: string, Location: string, Reason: string, ContactInfo: string}
     */
    private function infoFirma(array $material): array
    {
        return [
            'Name' => $material['titular'],
            'Location' => 'Ecuador',
            'Reason' => 'Firma electrónica del oficio',
            'ContactInfo' => 'SAVA ULEAM',
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

    /**
     * @param  array{titular: string, serial: string, cert: string, pkey: string, extracerts: string}  $material
     */
    private function agregarHojaFirmaElectronica(string $pdfBytes, array $material, string $fecha, ?string $imagenSello = null): string
    {
        $titular = $material['titular'];
        $serial = $material['serial'];

        return PdfFirmaDigital::aplicar(
            $pdfBytes,
            $material,
            function (PdfFirmador $pdf, int $page) use ($imagenSello): null {
                if ($page === 1) {
                    $this->estamparSelloAutorizado($pdf, $imagenSello);
                }

                return null;
            },
            $this->infoFirma($material),
            function (PdfFirmador $pdf) use ($titular, $fecha, $serial): array {
                $pdf->AddPage();
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->Cell(0, 10, 'Constancia de firma electrónica del solicitante', 0, 1);
                $pdf->SetFont('helvetica', '', 11);
                $pdf->Ln(4);
                $pdf->MultiCell(0, 6, "El documento anterior fue firmado electrónicamente mediante certificado digital (.p12) en SAVA.\n\nTitular del certificado: {$titular}\nFecha y hora: {$fecha}".($serial !== '' ? "\nN.º de serie: {$serial}" : '')."\n\nEl archivo .p12 no se almacena en el sistema; solo se usó en el momento de la firma.", 0, 'L');

                return ['x' => 15.0, 'y' => 20.0, 'w' => 180.0, 'h' => 70.0];
            },
        );
    }

    /**
     * @param  array{titular: string, serial: string, cert: string, pkey: string, extracerts: string}  $material
     */
    private function superponerBloqueFirmaElectronicaEnPdf(
        string $pdfBytes,
        Solicitud $solicitud,
        array $material,
        int $paginaObjetivo,
        float $xPorcentaje,
        float $yPorcentaje,
        ?string $imagenSello = null,
    ): string {
        $titular = $material['titular'];
        $serial = $material['serial'];
        $bloqueAncho = 72.0;
        $bloqueAlto = 22.0;
        $qrSize = 18.0;
        $aviso = (string) config('oficio.firma_electronica_aviso', 'Validar en SAVA ULEAM.');
        $qrPayload = $this->payloadQrFirmaElectronica($solicitud, $titular, $serial);
        [$nombreLinea1, $nombreLinea2] = $this->partirNombreTitularCertificado($titular);

        return PdfFirmaDigital::aplicar(
            $pdfBytes,
            $material,
            function (PdfFirmador $pdf, int $page, int $pageCount) use (
                $paginaObjetivo,
                $xPorcentaje,
                $yPorcentaje,
                $bloqueAncho,
                $bloqueAlto,
                $qrSize,
                $aviso,
                $qrPayload,
                $nombreLinea1,
                $nombreLinea2,
                $imagenSello,
            ): ?array {
                if ($page === 1) {
                    $this->estamparSelloAutorizado($pdf, $imagenSello);
                }

                $pagina = max(1, min($paginaObjetivo, $pageCount));
                if ($page !== $pagina) {
                    return null;
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

                return ['x' => $x, 'y' => $y, 'w' => $bloqueAncho, 'h' => $bloqueAlto];
            },
            $this->infoFirma($material),
        );
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
     * Estampa el sello al aprobar. La firma .p12 no se reescribe.
     * En la firma hecha a mano el sello se dibuja en un PDF nuevo y la firma se vuelve a trazar.
     */
    public function aplicarSelloDeAprobacion(Solicitud $solicitud, string $pdfBytes): string
    {
        $tipo = (string) (($solicitud->detalle['firma_docente']['tipo'] ?? ''));
        if ($tipo === 'manual') {
            return $this->estamparSelloOficioManual($pdfBytes);
        }

        return $this->superponerSelloAutorizado($pdfBytes);
    }

    /**
     * Estampa el sello al aprobar. Si el PDF ya está firmado, lo anexa al final y no reescribe la firma.
     */
    public function superponerSelloAutorizado(string $pdfBytes): string
    {
        $this->assertManipulacionPdfDisponible();
        $imagen = OficioSelloAutorizado::assetPath();
        if (! is_readable($imagen)) {
            return $pdfBytes;
        }

        if (PdfFirmaDigital::contieneFirmaDigital($pdfBytes)) {
            $recorte = $this->recorteSelloSinMargen($imagen);
            try {
                return PdfSelloIncremental::aplicar($pdfBytes, $recorte, 142.5, 34.0, 46.0, 46.0);
            } catch (\Throwable $e) {
                report($e);

                return $pdfBytes;
            } finally {
                if ($recorte !== $imagen) {
                    @unlink($recorte);
                }
            }
        }

        return $this->estamparSelloReescribiendo($pdfBytes);
    }

    private function estamparSelloOficioManual(string $pdfBytes): string
    {
        try {
            return $this->estamparSelloReescribiendo($pdfBytes);
        } catch (\Throwable $e) {
            report($e);

            return $pdfBytes;
        }
    }

    private function estamparSelloReescribiendo(string $pdfBytes): string
    {
        $this->assertManipulacionPdfDisponible();
        $imagen = OficioSelloAutorizado::assetPath();
        if (! is_readable($imagen)) {
            return $pdfBytes;
        }

        $pdf = new PdfConLector;
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);
        $reader = StreamReader::createByString($pdfBytes);
        $readerId = $pdf->setSourceFile($reader);

        $recorte = $this->recorteSelloSinMargen($imagen);
        $ancho = 46.0;
        $alto = 46.0;
        $x = 142.5;
        $y = 34.0;

        try {
            $pageCount = $pdf->setSourceFile($reader);
            for ($page = 1; $page <= $pageCount; $page++) {
                $this->importarPaginaPdf($pdf, $page);
                if ($page !== 1) {
                    continue;
                }

                $this->copiarFirmasVisuales($pdf, $readerId, $page);
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

    private function copiarFirmasVisuales(PdfConLector $pdf, string $readerId, int $numeroPagina): void
    {
        try {
            $pagina = $pdf->lector($readerId)->getPage($numeroPagina);
            $parser = (new \ReflectionProperty($pagina, 'parser'))->getValue($pagina);
            $anotaciones = PdfType::resolve(PdfDictionary::get($pagina->getPageDictionary(), 'Annots'), $parser);
        } catch (\Throwable) {
            return;
        }

        if (! $anotaciones instanceof PdfArray) {
            return;
        }

        $altoPagina = $pdf->GetPageHeight();
        $punto = 25.4 / 72;

        foreach ($anotaciones->value as $entrada) {
            try {
                $anotacion = PdfType::resolve($entrada, $parser);
                if (! $anotacion instanceof PdfDictionary) {
                    continue;
                }
                $subtipo = PdfType::resolve(PdfDictionary::get($anotacion, 'Subtype'), $parser);
                if (! $subtipo instanceof PdfName) {
                    continue;
                }
                if ($subtipo->value === 'Ink') {
                    $this->dibujarTinta($pdf, $anotacion, $parser, $altoPagina, $punto);
                } elseif (in_array($subtipo->value, ['Stamp', 'FreeText', 'Widget'], true)) {
                    $this->dibujarImagenDeApariencia($pdf, $anotacion, $parser, $altoPagina, $punto);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function dibujarTinta(Fpdi $pdf, PdfDictionary $anotacion, mixed $parser, float $altoPagina, float $punto): void
    {
        $lista = PdfType::resolve(PdfDictionary::get($anotacion, 'InkList'), $parser);
        if (! $lista instanceof PdfArray) {
            return;
        }

        [$rojo, $verde, $azul] = $this->colorDeAnotacion($anotacion, $parser);
        $pdf->SetDrawColor($rojo, $verde, $azul);
        $pdf->SetLineWidth($this->grosorDeAnotacion($anotacion, $parser, $punto));
        $pdf->SetLineCap(1);

        foreach ($lista->value as $trazo) {
            $trazo = PdfType::resolve($trazo, $parser);
            if (! $trazo instanceof PdfArray) {
                continue;
            }
            $puntos = [];
            foreach ($trazo->value as $numero) {
                $numero = PdfType::resolve($numero, $parser);
                if ($numero instanceof PdfNumeric) {
                    $puntos[] = (float) $numero->value;
                }
            }
            for ($i = 2; $i + 1 < count($puntos); $i += 2) {
                $pdf->Line(
                    $puntos[$i - 2] * $punto,
                    $altoPagina - ($puntos[$i - 1] * $punto),
                    $puntos[$i] * $punto,
                    $altoPagina - ($puntos[$i + 1] * $punto),
                );
            }
        }
    }

    private function dibujarImagenDeApariencia(Fpdi $pdf, PdfDictionary $anotacion, mixed $parser, float $altoPagina, float $punto): void
    {
        $imagen = $this->bytesDeImagen($anotacion, $parser);
        $rect = $this->rectangulo($anotacion, $parser);
        if ($imagen === null || $rect === null) {
            return;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'firma-manual-');
        if ($tmp === false) {
            return;
        }
        $ruta = $tmp.'.'.$imagen['ext'];
        @unlink($tmp);
        file_put_contents($ruta, $imagen['bytes']);
        try {
            $x = $rect[0] * $punto;
            $y = $altoPagina - ($rect[3] * $punto);
            $ancho = max(1.0, ($rect[2] - $rect[0]) * $punto);
            $alto = max(1.0, ($rect[3] - $rect[1]) * $punto);
            $pdf->Image($ruta, $x, $y, $ancho, $alto, strtoupper($imagen['ext']));
        } finally {
            @unlink($ruta);
        }
    }

    /**
     * @return array{bytes: string, ext: string}|null
     */
    private function bytesDeImagen(PdfDictionary $anotacion, mixed $parser, int $profundidad = 0): ?array
    {
        if ($profundidad > 5) {
            return null;
        }

        try {
            $apariencia = PdfType::resolve(PdfDictionary::get($anotacion, 'AP'), $parser);
            if (! $apariencia instanceof PdfDictionary) {
                return null;
            }
            $normal = PdfType::resolve(PdfDictionary::get($apariencia, 'N'), $parser);
        } catch (\Throwable) {
            return null;
        }

        return $this->buscarImagen($normal, $parser, $profundidad);
    }

    /**
     * @return array{bytes: string, ext: string}|null
     */
    private function buscarImagen(mixed $objeto, mixed $parser, int $profundidad): ?array
    {
        if ($profundidad > 5) {
            return null;
        }

        try {
            $objeto = PdfType::resolve($objeto, $parser);
        } catch (\Throwable) {
            return null;
        }

        if ($objeto instanceof PdfStream) {
            $diccionario = $objeto->value;
            if (! $diccionario instanceof PdfDictionary) {
                return null;
            }
            try {
                $subtipo = PdfType::resolve(PdfDictionary::get($diccionario, 'Subtype'), $parser);
            } catch (\Throwable) {
                $subtipo = null;
            }
            if ($subtipo instanceof PdfName && $subtipo->value === 'Image') {
                try {
                    $bytes = $objeto->getUnfilteredStream();
                } catch (\Throwable) {
                    return null;
                }
                $filtro = '';
                try {
                    $filtroTipo = PdfType::resolve(PdfDictionary::get($diccionario, 'Filter'), $parser);
                    $filtro = $filtroTipo instanceof PdfName ? $filtroTipo->value : '';
                } catch (\Throwable) {
                    $filtro = '';
                }
                $ext = $filtro === 'DCTDecode' ? 'jpg' : 'png';
                if ($filtro !== 'DCTDecode' && ! str_starts_with($bytes, "\x89PNG")) {
                    return null;
                }

                return ['bytes' => $bytes, 'ext' => $ext];
            }

            try {
                $recursos = PdfType::resolve(PdfDictionary::get($diccionario, 'Resources'), $parser);
                if ($recursos instanceof PdfDictionary) {
                    $xobjects = PdfType::resolve(PdfDictionary::get($recursos, 'XObject'), $parser);
                    if ($xobjects instanceof PdfDictionary) {
                        foreach ($xobjects->value as $candidato) {
                            $hallada = $this->buscarImagen($candidato, $parser, $profundidad + 1);
                            if ($hallada !== null) {
                                return $hallada;
                            }
                        }
                    }
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    private function rectangulo(PdfDictionary $anotacion, mixed $parser): ?array
    {
        try {
            $rect = PdfType::resolve(PdfDictionary::get($anotacion, 'Rect'), $parser);
        } catch (\Throwable) {
            return null;
        }
        if (! $rect instanceof PdfArray || count($rect->value) !== 4) {
            return null;
        }

        $valores = [];
        foreach ($rect->value as $numero) {
            $numero = PdfType::resolve($numero, $parser);
            if (! $numero instanceof PdfNumeric) {
                return null;
            }
            $valores[] = (float) $numero->value;
        }

        return [$valores[0], $valores[1], $valores[2], $valores[3]];
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function colorDeAnotacion(PdfDictionary $anotacion, mixed $parser): array
    {
        try {
            $color = PdfType::resolve(PdfDictionary::get($anotacion, 'C'), $parser);
            if ($color instanceof PdfArray && count($color->value) >= 3) {
                $canales = [];
                foreach (array_slice($color->value, 0, 3) as $canal) {
                    $canal = PdfType::resolve($canal, $parser);
                    $canales[] = $canal instanceof PdfNumeric ? (int) round(((float) $canal->value) * 255) : 0;
                }

                return [$canales[0], $canales[1], $canales[2]];
            }
        } catch (\Throwable) {
            // La tinta sin color se dibuja en negro.
        }

        return [0, 0, 0];
    }

    private function grosorDeAnotacion(PdfDictionary $anotacion, mixed $parser, float $punto): float
    {
        try {
            $borde = PdfType::resolve(PdfDictionary::get($anotacion, 'BS'), $parser);
            if ($borde instanceof PdfDictionary) {
                $ancho = PdfType::resolve(PdfDictionary::get($borde, 'W'), $parser);
                if ($ancho instanceof PdfNumeric) {
                    return max(0.3, (float) $ancho->value * $punto);
                }
            }
        } catch (\Throwable) {
            // Grosor por defecto.
        }

        return 0.6;
    }

    private function prepararRecorteSello(): ?string
    {
        $imagen = OficioSelloAutorizado::assetPath();
        if (! is_readable($imagen)) {
            return null;
        }

        return $this->recorteSelloSinMargen($imagen);
    }

    private function descartarRecorteSello(?string $recorte): void
    {
        if ($recorte !== null && $recorte !== OficioSelloAutorizado::assetPath() && is_file($recorte)) {
            @unlink($recorte);
        }
    }

    private function estamparSelloAutorizado(PdfFirmador $pdf, ?string $imagen): void
    {
        if ($imagen === null || ! is_readable($imagen)) {
            return;
        }

        $ancho = 46.0;
        $alto = 46.0;
        $x = 142.5;
        $y = 34.0;
        $pageW = $pdf->GetPageWidth();
        $pageH = $pdf->GetPageHeight();
        $xPagina = max(4.0, min($x, $pageW - $ancho - 4));
        $yPagina = max(4.0, min($y, $pageH - $alto - 4));
        $pdf->Image($imagen, $xPagina, $yPagina, $ancho, $alto, 'JPEG');
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

    /**
     * @param  array{titular: string, serial: string, cert: string, pkey: string, extracerts: string}  $material
     */
    private function superponerSelloYFirmarEnPdf(
        string $pdfBytes,
        string $pngPath,
        array $material,
        int $paginaObjetivo,
        float $xPorcentaje,
        float $yPorcentaje,
        ?string $imagenSello = null,
    ): string {
        return PdfFirmaDigital::aplicar(
            $pdfBytes,
            $material,
            function (PdfFirmador $pdf, int $page, int $pageCount) use ($pngPath, $paginaObjetivo, $xPorcentaje, $yPorcentaje, $imagenSello): ?array {
                if ($page === 1) {
                    $this->estamparSelloAutorizado($pdf, $imagenSello);
                }

                $pagina = max(1, min($paginaObjetivo, $pageCount));
                if ($page !== $pagina) {
                    return null;
                }

                $anchoFirma = 45.0;
                $altoFirma = 16.0;
                $x = max(4.0, ($xPorcentaje / 100) * $pdf->GetPageWidth() - ($anchoFirma / 2));
                $y = max(4.0, ($yPorcentaje / 100) * $pdf->GetPageHeight() - ($altoFirma / 2));
                $pdf->Image($pngPath, $x, $y, $anchoFirma, $altoFirma, 'PNG');

                return ['x' => $x, 'y' => $y, 'w' => $anchoFirma, 'h' => $altoFirma];
            },
            $this->infoFirma($material),
        );
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
