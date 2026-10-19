<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Models\User;
use App\Support\OficioSelloAutorizado;
use App\Support\OficioSelloFaltanteException;
use Illuminate\Support\Facades\File;
use ZipArchive;

class OficioDocxService
{
    public function __construct(private readonly OficioDatosService $datos) {}

    public function puedeGenerarDocx(Solicitud $solicitud): bool
    {
        return $this->datos->plantillaPath($solicitud->tipo) !== null;
    }

    public function generar(Solicitud $solicitud, ?User $decano = null): string
    {
        $plantilla = $this->datos->plantillaPath($solicitud->tipo);
        if ($plantilla === null) {
            throw new \InvalidArgumentException('No hay plantilla DOCX para este tipo de solicitud.');
        }

        $valores = $this->datos->placeholders($solicitud, $decano);
        $correoDocente = (string) ($valores['_correo_docente'] ?? '');
        $correoDecano = (string) ($valores['_correo_decano'] ?? '');
        unset($valores['_correo_docente'], $valores['_correo_decano']);

        $tempIn = tempnam(sys_get_temp_dir(), 'oficio-src-');
        $tempOut = tempnam(sys_get_temp_dir(), 'oficio-out-');
        File::copy($plantilla, $tempIn);

        $zip = new ZipArchive;
        if ($zip->open($tempOut, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            @unlink($tempIn);
            @unlink($tempOut);
            throw new \RuntimeException('No se pudo preparar el documento.');
        }

        $source = new ZipArchive;
        if ($source->open($tempIn) !== true) {
            $zip->close();
            @unlink($tempIn);
            @unlink($tempOut);
            throw new \RuntimeException('No se pudo abrir la plantilla.');
        }

        if ($decano !== null && ! OficioSelloAutorizado::puedeAplicar()) {
            throw OficioSelloFaltanteException::make();
        }

        $aplicarSello = $decano !== null;
        $documentXml = null;
        $documentRels = null;
        $contentTypes = null;
        $selloRelId = null;

        for ($i = 0; $i < $source->numFiles; $i++) {
            $name = $source->getNameIndex($i);
            $content = $source->getFromIndex($i);
            if ($content === false || ! is_string($name)) {
                continue;
            }

            if ($name === 'word/document.xml') {
                $documentXml = $this->rellenarXml($content, $valores, $correoDocente, $correoDecano);
                if (! $aplicarSello) {
                    $zip->addFromString($name, $documentXml);
                }

                continue;
            }

            if ($aplicarSello && $name === 'word/_rels/document.xml.rels') {
                $documentRels = $content;

                continue;
            }

            if ($aplicarSello && $name === '[Content_Types].xml') {
                $contentTypes = $content;

                continue;
            }

            if (str_ends_with(strtolower($name), '.xml')) {
                $zip->addFromString($name, $this->rellenarXml($content, $valores, $correoDocente, $correoDecano));
            } else {
                $zip->addFromString($name, $content);
            }
        }

        if ($aplicarSello && is_string($documentXml) && is_string($documentRels) && is_string($contentTypes)) {
            $selloRelId = OficioSelloAutorizado::siguienteRelId($documentRels);
            $documentXml = OficioSelloAutorizado::aplicarDocumento($documentXml, $selloRelId);
            $documentRels = OficioSelloAutorizado::agregarRelacionImagen($documentRels, $selloRelId);
            $contentTypes = OficioSelloAutorizado::agregarContentTypeJpeg($contentTypes);

            $zip->addFromString('word/document.xml', $documentXml);
            $zip->addFromString('word/_rels/document.xml.rels', $documentRels);
            $zip->addFromString('[Content_Types].xml', $contentTypes);
            $zip->addFromString(
                OficioSelloAutorizado::MEDIA_PATH,
                (string) file_get_contents(OficioSelloAutorizado::assetPath()),
            );
        } elseif (is_string($documentXml)) {
            $zip->addFromString('word/document.xml', $documentXml);
        }

        $source->close();
        $zip->close();
        @unlink($tempIn);

        $binary = file_get_contents($tempOut) ?: '';
        @unlink($tempOut);

        return $binary;
    }

    /**
     * @param  array<string, string>  $valores
     */
    private function rellenarXml(string $xml, array $valores, string $correoDocente, string $correoDecano): string
    {
        $xml = OficioDatosService::aplicarFechaLugar($xml);
        $xml = $this->insertarCorreosInstitucionales($xml, $correoDocente, $correoDecano);

        $codigo = $valores['[Código del oficio]'] ?? '';
        if ($codigo !== '') {
            $xml = str_replace('FACIVITEC-MMAAAA-NNNN', $codigo, $xml);
            $xml = str_replace('FACIVITEC-032026-0005', $codigo, $xml);
            $xml = str_replace('-MMAAAA-NNNN', '-'.substr($codigo, strlen('FACIVITEC-')), $xml);
            $xml = preg_replace('/FACIVITEC-ADM-[A-Z0-9]+-\d+/u', $codigo, $xml, 1) ?? $xml;
        }

        $fechasEvento = $valores['[Fechas del evento o actividad]'] ?? null;
        if ($fechasEvento !== null && $fechasEvento !== '') {
            $xml = preg_replace(
                '/Fechas del evento o actividad:\s*desde el[\s\S]{0,800}?de[\s\S]{0,120}?_\s*_\s*_\s*_\s*_/u',
                'Fechas del evento o actividad: '.$this->xmlEscape($fechasEvento),
                $xml,
                1,
            ) ?? $xml;
        }

        uksort($valores, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $buscar = array_keys($valores);
        $reemplazar = array_map(fn (string $v): string => $this->xmlEscape($v), array_values($valores));

        $xml = str_replace($buscar, $reemplazar, $xml);
        $xml = preg_replace('/<w:color w:val="[^"]*"\/>/u', '', $xml) ?? $xml;
        $xml = preg_replace('/<w:highlight w:val="[^"]*"\/>/u', '', $xml) ?? $xml;
        $xml = preg_replace('/<w:shd\b[^>]*\/>/u', '', $xml) ?? $xml;

        return $this->mantenerCorreoEnUnaLinea($this->normalizarTablaFirma($xml));
    }

    /**
     * Word y docx-preview expanden espacios con justificación; en la fila de datos
     * de firma eso separa «Correo» e «Institucional:» cuando el correo es largo.
     */
    private function insertarCorreosInstitucionales(string $xml, string $correoDocente, string $correoDecano): string
    {
        if ($correoDocente !== '') {
            $xml = preg_replace(
                '/Correo Institucional:(?:\s|<[^>]+>)*\[Correo\]/u',
                'Correo Institucional: '.$this->xmlEscape($correoDocente),
                $xml,
                1,
            ) ?? $xml;

            $xml = preg_replace(
                '/\[Correo\]/',
                $this->xmlEscape($correoDocente),
                $xml,
                1,
            ) ?? $xml;
        }

        if ($correoDecano !== '') {
            $xml = preg_replace(
                '/Correo Institucional:(?:\s|<[^>]+>)*\[Correo\]/u',
                'Correo Institucional: '.$this->xmlEscape($correoDecano),
                $xml,
                1,
            ) ?? $xml;

            $xml = preg_replace(
                '/\[Correo\]/',
                $this->xmlEscape($correoDecano),
                $xml,
                1,
            ) ?? $xml;
        }

        return $xml;
    }

    private function normalizarTablaFirma(string $xml): string
    {
        if (! str_contains($xml, 'Aprobado por')) {
            return $xml;
        }

        $result = preg_replace_callback(
            '/<w:tbl\b[^>]*>(?:(?!<\/w:tbl>).)*Aprobado por:(?:(?!<\/w:tbl>).)*<\/w:tbl>/su',
            static function (array $matches): string {
                return str_replace(
                    ['<w:jc w:val="both"/>', '<w:jc w:val="distribute"/>'],
                    '<w:jc w:val="left"/>',
                    $matches[0],
                );
            },
            $xml,
            1,
        );

        return is_string($result) ? $result : $xml;
    }

    /**
     * La fila de firma es estrecha y está justificada, así que «Correo Institucional»
     * se abre y la dirección baja a la línea siguiente.
     */
    private function mantenerCorreoEnUnaLinea(string $xml): string
    {
        $ancho = $this->anchoUtilPagina($xml);

        $result = preg_replace_callback(
            '/<w:tbl\b[^>]*>(?:(?!<\/w:tbl>).)*Correo Institucional:(?:(?!<\/w:tbl>).)*<\/w:tbl>/su',
            function (array $matches) use ($ancho): string {
                $tabla = $matches[0];
                if ($ancho !== null) {
                    $tabla = preg_replace(
                        '/<w:gridCol w:w="\d+"\/>/',
                        '<w:gridCol w:w="'.$ancho.'"/>',
                        $tabla,
                    ) ?? $tabla;
                    $tabla = preg_replace(
                        '/<w:tcW w:w="\d+" w:type="dxa"\/>/',
                        '<w:tcW w:w="'.$ancho.'" w:type="dxa"/>',
                        $tabla,
                    ) ?? $tabla;
                }

                $parrafo = preg_replace_callback(
                    '/<w:p\b[^>]*>(?:(?!<\/w:p>).)*Correo Institucional:(?:(?!<\/w:p>).)*<\/w:p>/su',
                    static function (array $coincidencias): string {
                        $bloque = $coincidencias[0];
                        if (str_contains($bloque, '<w:jc ')) {
                            $bloque = preg_replace('/<w:jc w:val="[^"]*"\/>/', '<w:jc w:val="left"/>', $bloque) ?? $bloque;
                        }

                        return preg_replace(
                            '/Correo Institucional:\s*/u',
                            "Correo\u{00A0}Institucional:\u{00A0}",
                            $bloque,
                            1,
                        ) ?? $bloque;
                    },
                    $tabla,
                );

                return is_string($parrafo) ? $parrafo : $tabla;
            },
            $xml,
        );

        return is_string($result) ? $result : $xml;
    }

    private function anchoUtilPagina(string $xml): ?int
    {
        if (! preg_match('/<w:pgSz\b[^>]*\bw:w="(\d+)"/', $xml, $pagina)) {
            return null;
        }

        if (! preg_match('/<w:pgMar\b[^>]*>/', $xml, $margen)) {
            return null;
        }

        preg_match('/\bw:left="(\d+)"/', $margen[0], $izquierdo);
        preg_match('/\bw:right="(\d+)"/', $margen[0], $derecho);

        $ancho = (int) $pagina[1] - (int) ($izquierdo[1] ?? 0) - (int) ($derecho[1] ?? 0);

        return $ancho > 0 ? $ancho : null;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
