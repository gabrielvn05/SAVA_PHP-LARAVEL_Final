<?php

namespace App\Support;

/**
 * Agrega el sello como una revisión nueva al final del PDF.
 * No toca los bytes cubiertos por la firma, así FirmaEC sigue validándola.
 */
class PdfSelloIncremental
{
    public static function aplicar(string $pdf, string $jpegPath, float $xMm, float $yMm, float $anchoMm, float $altoMm): string
    {
        if (! is_readable($jpegPath)) {
            throw new \RuntimeException('No se encontró la imagen del sello.');
        }

        $jpeg = file_get_contents($jpegPath);
        $info = @getimagesize($jpegPath);
        if (! is_string($jpeg) || $jpeg === '' || ! is_array($info)) {
            throw new \RuntimeException('No se pudo leer la imagen del sello.');
        }

        [$mapa, $trailer] = self::mapaYTrailer($pdf);
        $raiz = self::referencia($trailer, 'Root');
        $paginasId = self::referencia(self::diccionario($pdf, $mapa, $raiz), 'Pages');
        $paginaId = self::primeraPagina($pdf, $mapa, $paginasId);
        $pagina = self::diccionario($pdf, $mapa, $paginaId);
        $caja = self::mediaBox($pagina);

        $mm = 72 / 25.4;
        $ancho = $anchoMm * $mm;
        $alto = $altoMm * $mm;
        $x = $xMm * $mm;
        $y = $caja[3] - ($yMm * $mm) - $alto;

        $size = self::referenciaNumero($trailer, 'Size') ?? (max(array_keys($mapa)) + 1);
        $imagenId = $size;
        $contenidoId = $size + 1;
        $recursosId = $size + 2;
        $paginaNuevaId = $size + 3;

        $recursos = self::recursosConSello($pdf, $mapa, $pagina, $imagenId);
        $paginaNueva = self::reemplazarReferencia($pagina, 'Resources', $recursosId);
        $paginaNueva = self::agregarContenido($paginaNueva, $contenidoId);

        $objetos = [
            $imagenId => self::objetoImagen($info, $jpeg),
            $contenidoId => self::objetoContenido($x, $y, $ancho, $alto),
            $recursosId => $recursos,
        ];

        $nodoViejo = $paginaId;
        $nodoNuevo = $paginaNuevaId;
        $paginasNuevasId = $paginaNuevaId;
        $siguiente = $size + 4;
        $padreViejo = self::referencia($pagina, 'Parent');

        while ($padreViejo !== null) {
            $padreNuevo = $siguiente;
            $siguiente++;
            $padre = self::diccionario($pdf, $mapa, $padreViejo);
            $padre = preg_replace(
                '/(?<!\d)'.preg_quote((string) $nodoViejo, '/').'\s+0\s+R/',
                $nodoNuevo.' 0 R',
                $padre,
                1,
            );
            if (! is_string($padre)) {
                throw new \RuntimeException('No se pudo actualizar el árbol de páginas.');
            }
            $abuelo = self::referencia($padre, 'Parent');
            if ($abuelo !== null) {
                $padre = self::reemplazarReferencia($padre, 'Parent', $siguiente);
            }
            $objetos[$padreNuevo] = $padre;
            $paginasNuevasId = $padreNuevo;
            $nodoViejo = $padreViejo;
            $nodoNuevo = $padreNuevo;
            $padreViejo = $abuelo;
        }

        $paginaNueva = self::reemplazarReferencia($paginaNueva, 'Parent', $size + 4);
        $objetos[$paginaNuevaId] = $paginaNueva;

        $catalogoId = $siguiente;
        $catalogo = self::diccionario($pdf, $mapa, $raiz);
        $catalogo = self::reemplazarReferencia($catalogo, 'Pages', $paginasNuevasId);
        $catalogo = preg_replace(
            '/\/OpenAction\s*\[\s*'.preg_quote((string) $paginaId, '/').'\s+0\s+R/',
            '/OpenAction ['.$paginaNuevaId.' 0 R',
            $catalogo,
            1,
        );
        if (! is_string($catalogo)) {
            throw new \RuntimeException('No se pudo actualizar el catálogo del PDF.');
        }
        $objetos[$catalogoId] = $catalogo;

        ksort($objetos);

        return self::anexar($pdf, $objetos, $catalogoId, $trailer);
    }

    /**
     * @return array{0: array<int, int>, 1: string}
     */
    private static function mapaYTrailer(string $pdf): array
    {
        $inicio = self::startxref($pdf);
        $mapa = [];
        $trailer = '';
        $vistos = [];

        while ($inicio !== null && ! isset($vistos[$inicio])) {
            $vistos[$inicio] = true;
            if (substr($pdf, $inicio, 4) !== 'xref') {
                throw new \RuntimeException('El PDF firmado no tiene una tabla de referencias clásica.');
            }

            $cursor = $inicio + 4;
            $cursor = self::saltarEspacio($pdf, $cursor);

            while (preg_match('/\G(\d+)\s+(\d+)\s*(?:\r\n|\n|\r)/', $pdf, $seccion, 0, $cursor)) {
                $primero = (int) $seccion[1];
                $cantidad = (int) $seccion[2];
                $cursor += strlen($seccion[0]);
                for ($i = 0; $i < $cantidad; $i++) {
                    $linea = substr($pdf, $cursor, 20);
                    if (! preg_match('/^(\d{10}) (\d{5}) ([nf])/', $linea, $entrada)) {
                        throw new \RuntimeException('Tabla de referencias del PDF incompleta.');
                    }
                    $numero = $primero + $i;
                    if ($entrada[3] === 'n' && ! isset($mapa[$numero])) {
                        $mapa[$numero] = (int) $entrada[1];
                    }
                    $cursor += 20;
                }
                $cursor = self::saltarEspacio($pdf, $cursor);
            }

            $trailerPos = strpos($pdf, 'trailer', $cursor);
            if ($trailerPos === false) {
                throw new \RuntimeException('El PDF firmado no tiene trailer.');
            }
            $fin = strpos($pdf, 'startxref', $trailerPos);
            $este = substr($pdf, $trailerPos + 7, ($fin === false ? 400 : $fin - $trailerPos - 7));
            if ($trailer === '') {
                $trailer = $este;
            }
            $inicio = self::referencia($este, 'Prev');
        }

        if ($mapa === [] || $trailer === '') {
            throw new \RuntimeException('No se pudo leer la estructura del PDF firmado.');
        }

        return [$mapa, $trailer];
    }

    private static function startxref(string $pdf): int
    {
        $pos = strrpos($pdf, 'startxref');
        if ($pos === false || ! preg_match('/(\d+)/', substr($pdf, $pos + 9, 30), $coincidencia)) {
            throw new \RuntimeException('El PDF firmado no tiene startxref.');
        }

        return (int) $coincidencia[1];
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function diccionario(string $pdf, array $mapa, int $id): string
    {
        $offset = $mapa[$id] ?? null;
        if ($offset === null) {
            throw new \RuntimeException('Falta un objeto del PDF firmado.');
        }

        if (! preg_match('/^\d+\s+\d+\s+obj\s*/', substr($pdf, $offset, 40), $cabecera)) {
            throw new \RuntimeException('Objeto PDF ilegible.');
        }

        $desde = $offset + strlen($cabecera[0]);
        $inicio = strpos($pdf, '<<', $desde);
        if ($inicio === false) {
            throw new \RuntimeException('El objeto PDF no es un diccionario.');
        }

        return self::extraerDiccionario($pdf, $inicio);
    }

    private static function extraerDiccionario(string $pdf, int $inicio): string
    {
        $profundidad = 0;
        $largo = strlen($pdf);
        $i = $inicio;
        $enTexto = false;

        while ($i < $largo) {
            $caracter = $pdf[$i];
            if ($enTexto) {
                if ($caracter === '\\') {
                    $i += 2;

                    continue;
                }
                if ($caracter === ')') {
                    $enTexto = false;
                }
                $i++;

                continue;
            }
            if ($caracter === '(') {
                $enTexto = true;
                $i++;

                continue;
            }
            if ($caracter === '<' && ($pdf[$i + 1] ?? '') === '<') {
                $profundidad++;
                $i += 2;

                continue;
            }
            if ($caracter === '>' && ($pdf[$i + 1] ?? '') === '>') {
                $profundidad--;
                $i += 2;
                if ($profundidad === 0) {
                    return substr($pdf, $inicio, $i - $inicio);
                }

                continue;
            }
            $i++;
        }

        throw new \RuntimeException('Diccionario PDF incompleto.');
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function primeraPagina(string $pdf, array $mapa, int $nodo): int
    {
        $visto = [];
        while (! isset($visto[$nodo])) {
            $visto[$nodo] = true;
            $dict = self::diccionario($pdf, $mapa, $nodo);
            if (str_contains($dict, '/Type /Pages')) {
                if (! preg_match('/\/Kids\s*\[(.*?)\]/s', $dict, $hijos) || ! preg_match('/(\d+)\s+0\s+R/', $hijos[1], $primero)) {
                    throw new \RuntimeException('El PDF no tiene páginas legibles.');
                }
                $nodo = (int) $primero[1];

                continue;
            }
            if (str_contains($dict, '/Type /Page')) {
                return $nodo;
            }

            throw new \RuntimeException('No se encontró la primera página del PDF.');
        }

        throw new \RuntimeException('El árbol de páginas del PDF está en bucle.');
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function mediaBox(string $pagina): array
    {
        if (! preg_match('/\/MediaBox\s*\[\s*([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s*\]/', $pagina, $caja)) {
            return [0.0, 0.0, 595.276, 841.890];
        }

        return [(float) $caja[1], (float) $caja[2], (float) $caja[3], (float) $caja[4]];
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function recursosConSello(string $pdf, array $mapa, string $pagina, int $imagenId): string
    {
        if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $pagina, $referencia)) {
            $recursos = self::diccionario($pdf, $mapa, (int) $referencia[1]);
        } elseif (preg_match('/\/Resources\s*(<<)/', $pagina, $inline, PREG_OFFSET_CAPTURE)) {
            $recursos = self::extraerDiccionario($pagina, $inline[1][1]);
        } else {
            $recursos = '<< >>';
        }

        if (preg_match('/\/XObject\s*<</', $recursos, $xobject, PREG_OFFSET_CAPTURE)) {
            $corte = $xobject[0][1] + strlen($xobject[0][0]);

            return substr($recursos, 0, $corte).' /SelloSAVA '.$imagenId.' 0 R'.substr($recursos, $corte);
        }

        $cierre = strrpos($recursos, '>>');
        if ($cierre === false) {
            throw new \RuntimeException('Los recursos de la página no son válidos.');
        }

        return substr($recursos, 0, $cierre).' /XObject << /SelloSAVA '.$imagenId.' 0 R >> >>';
    }

    private static function agregarContenido(string $pagina, int $contenidoId): string
    {
        if (preg_match('/\/Contents\s*\[([^\]]*)\]/', $pagina, $lista)) {
            $interno = rtrim($lista[1]).' '.$contenidoId.' 0 R';

            return str_replace($lista[0], '/Contents ['.$interno.']', $pagina);
        }

        $actualizado = preg_replace(
            '/\/Contents\s+(\d+\s+0\s+R)/',
            '/Contents [$1 '.$contenidoId.' 0 R]',
            $pagina,
            1,
        );
        if (! is_string($actualizado) || $actualizado === $pagina) {
            throw new \RuntimeException('La página firmada no tiene contenido.');
        }

        return $actualizado;
    }

    /**
     * @param  array<int, int>  $info
     */
    private static function objetoImagen(array $info, string $jpeg): string
    {
        $canales = (int) ($info['channels'] ?? 3);
        $espacio = match ($canales) {
            1 => '/DeviceGray',
            4 => '/DeviceCMYK',
            default => '/DeviceRGB',
        };
        $ancho = (int) ($info[0] ?? 0);
        $alto = (int) ($info[1] ?? 0);

        return '<< /Type /XObject /Subtype /Image /Width '.$ancho.' /Height '.$alto
            .' /ColorSpace '.$espacio.' /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($jpeg)." >>\n"
            ."stream\n".$jpeg."\nendstream";
    }

    private static function objetoContenido(float $x, float $y, float $ancho, float $alto): string
    {
        $contenido = 'q '.self::num($ancho).' 0 0 '.self::num($alto).' '.self::num($x).' '.self::num($y)." cm /SelloSAVA Do Q\n";

        return "<< /Length ".strlen($contenido)." >>\nstream\n".$contenido."endstream";
    }

    private static function reemplazarReferencia(string $dict, string $clave, int $id): string
    {
        $actualizado = preg_replace(
            '/\/'.preg_quote($clave, '/').'\s+\d+\s+0\s+R/',
            '/'.$clave.' '.$id.' 0 R',
            $dict,
            1,
        );
        if (! is_string($actualizado)) {
            throw new \RuntimeException('No se pudo actualizar el PDF firmado.');
        }

        return $actualizado;
    }

    private static function referencia(string $dict, string $clave): ?int
    {
        if (! preg_match('/\/'.preg_quote($clave, '/').'\s+(\d+)\s+0\s+R/', $dict, $coincidencia)) {
            return null;
        }

        return (int) $coincidencia[1];
    }

    private static function referenciaNumero(string $dict, string $clave): ?int
    {
        if (! preg_match('/\/'.preg_quote($clave, '/').'\s+(\d+)/', $dict, $coincidencia)) {
            return null;
        }

        return (int) $coincidencia[1];
    }

    /**
     * @param  array<int, string>  $objetos
     */
    private static function anexar(string $pdf, array $objetos, int $catalogoId, string $trailerViejo): string
    {
        $salida = $pdf;
        $desplazamiento = strlen($salida);
        if (! str_ends_with($salida, "\n")) {
            $salida .= "\n";
            $desplazamiento++;
        }

        $offsets = [];
        foreach ($objetos as $id => $cuerpo) {
            $offsets[$id] = $desplazamiento;
            $pieza = $id." 0 obj\n".$cuerpo."\nendobj\n";
            $salida .= $pieza;
            $desplazamiento += strlen($pieza);
        }

        $ids = array_keys($offsets);
        $xrefEn = $desplazamiento;
        $xref = 'xref'."\n".$ids[0].' '.count($ids)."\n";
        foreach ($ids as $id) {
            $xref .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $size = max($ids) + 1;
        $trailer = '<< /Size '.$size.' /Root '.$catalogoId.' 0 R /Prev '.self::startxref($pdf);
        $info = self::referencia($trailerViejo, 'Info');
        if ($info !== null) {
            $trailer .= ' /Info '.$info.' 0 R';
        }
        if (preg_match('/\/ID\s*\[.*?\]/s', $trailerViejo, $identificador)) {
            $trailer .= ' '.$identificador[0];
        }
        $trailer .= " >>\n";

        return $salida.$xref."trailer\n".$trailer.'startxref'."\n".$xrefEn."\n%%EOF\n";
    }

    private static function saltarEspacio(string $pdf, int $cursor): int
    {
        $largo = strlen($pdf);
        while ($cursor < $largo && ($pdf[$cursor] === ' ' || $pdf[$cursor] === "\n" || $pdf[$cursor] === "\r" || $pdf[$cursor] === "\t")) {
            $cursor++;
        }

        return $cursor;
    }

    private static function num(float $valor): string
    {
        return number_format($valor, 3, '.', '');
    }
}
