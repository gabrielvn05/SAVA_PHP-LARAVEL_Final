<?php

namespace App\Support;

use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

class PdfFirmador extends Fpdi
{
    public function reservarEspacioFirma(int $longitudHex): void
    {
        $this->signature_max_length = $longitudHex;
    }
}

class PdfFirmaDigital
{
    /** Hex del PKCS#7. Los certificados de Ecuador con cadena superan el límite de TCPDF. */
    public const ESPACIO_FIRMA_HEX = 65536;

    public static function contieneFirmaDigital(string $pdf): bool
    {
        if (! str_contains($pdf, '/ByteRange')) {
            return false;
        }

        return str_contains($pdf, '/Type /Sig')
            || str_contains($pdf, '/Type/Sig')
            || str_contains($pdf, 'adbe.pkcs7.detached')
            || str_contains($pdf, 'ETSI.CAdES.detached');
    }

    /**
     * Incrusta una firma PKCS#7 separada (PAdES que FirmaEC reconoce).
     *
     * @param  array{cert: string, pkey: string, extracerts?: string}  $material
     * @param  callable(PdfFirmador, int, int): ?array{x: float, y: float, w: float, h: float}  $dibujar
     * @param  array{Name?: string, Location?: string, Reason?: string, ContactInfo?: string}  $info
     * @param  (callable(PdfFirmador): ?array{x: float, y: float, w: float, h: float})|null  $alFinal
     */
    public static function aplicar(string $pdfBytes, array $material, callable $dibujar, array $info, ?callable $alFinal = null): string
    {
        $extraArchivo = null;

        try {
            $extraRuta = '';
            $cadena = trim((string) ($material['extracerts'] ?? ''));
            if ($cadena !== '') {
                $extraArchivo = tempnam(sys_get_temp_dir(), 'sava-cadena-');
                if ($extraArchivo === false) {
                    throw new \RuntimeException('No se pudo preparar la cadena del certificado.');
                }
                file_put_contents($extraArchivo, $cadena);
                $extraRuta = str_replace('\\', '/', $extraArchivo);
            }

            $pdf = new PdfFirmador;
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);
            $pdf->SetCreator('SAVA ULEAM');
            $pdf->reservarEspacioFirma(self::ESPACIO_FIRMA_HEX);
            $pdf->setSignature(
                (string) $material['cert'],
                (string) $material['pkey'],
                '',
                $extraRuta,
                1,
                $info,
                'A',
            );

            $reader = StreamReader::createByString($pdfBytes);
            $pageCount = $pdf->setSourceFile($reader);
            $tieneRecuadro = false;

            for ($page = 1; $page <= $pageCount; $page++) {
                self::importarPagina($pdf, $page);
                $rect = $dibujar($pdf, $page, $pageCount);
                if (is_array($rect)) {
                    $pdf->setSignatureAppearance($rect['x'], $rect['y'], $rect['w'], $rect['h'], $page, 'FirmaSAVA');
                    $tieneRecuadro = true;
                }
            }

            if ($alFinal !== null) {
                $rect = $alFinal($pdf);
                if (is_array($rect)) {
                    $pdf->setSignatureAppearance($rect['x'], $rect['y'], $rect['w'], $rect['h'], -1, 'FirmaSAVA');
                    $tieneRecuadro = true;
                }
            }

            if (! $tieneRecuadro) {
                throw new \RuntimeException('No se definió el recuadro de la firma.');
            }

            $firmado = $pdf->Output('', 'S');
            self::assertFirmaIncrustada($firmado);

            return $firmado;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo firmar el PDF: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_string($extraArchivo) && is_file($extraArchivo)) {
                @unlink($extraArchivo);
            }
        }
    }

    private static function importarPagina(PdfFirmador $pdf, int $page): void
    {
        $template = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($template);
        $orientation = $size['orientation'] ?? ((($size['width'] ?? 0) > ($size['height'] ?? 0)) ? 'L' : 'P');
        $pdf->AddPage($orientation, [$size['width'], $size['height']]);
        $pdf->useTemplate($template, 0, 0, $size['width'], $size['height'], true);
    }

    private static function assertFirmaIncrustada(string $pdf): void
    {
        if (! self::contieneFirmaDigital($pdf)) {
            throw new \RuntimeException('El PDF no quedó con un campo de firma digital.');
        }

        if (! preg_match_all('/\/Contents<([0-9A-Fa-f]+)>/', $pdf, $coincidencias)) {
            throw new \RuntimeException('El PDF no contiene el PKCS#7 de la firma.');
        }

        $hex = '';
        foreach ($coincidencias[1] as $candidato) {
            if (strlen($candidato) === self::ESPACIO_FIRMA_HEX) {
                $hex = $candidato;
                break;
            }
        }

        if ($hex === '' || trim($hex, '0') === '') {
            $openssl = [];
            while ($mensaje = openssl_error_string()) {
                $openssl[] = $mensaje;
            }
            $detalle = $openssl !== [] ? ' '.implode(' ', $openssl) : '';

            throw new \RuntimeException('OpenSSL no pudo incrustar la firma en el PDF.'.$detalle);
        }
    }
}
