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

        $fuente = $pdf;
        [$mapa, $trailer, $fuente] = self::mapaYTrailer($fuente);
        $raiz = self::referencia($trailer, 'Root');
        if ($raiz === null) {
            throw new \RuntimeException('El PDF firmado no tiene catálogo.');
        }
        $paginasId = self::referencia(self::diccionario($fuente, $mapa, $raiz), 'Pages');
        if ($paginasId === null) {
            throw new \RuntimeException('El PDF firmado no tiene páginas.');
        }
        $paginaId = self::primeraPagina($fuente, $mapa, $paginasId);
        $pagina = self::diccionario($fuente, $mapa, $paginaId);
        $caja = self::mediaBoxResuelto($fuente, $mapa, $pagina);

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

        $recursos = self::recursosConSello($fuente, $mapa, $pagina, $imagenId);
        $paginaNueva = self::ponerReferencia($pagina, 'Resources', $recursosId);
        $paginaNueva = self::asegurarContenido($fuente, $mapa, $paginaNueva);
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
            $padre = self::diccionario($fuente, $mapa, $padreViejo);
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
        $firmasMovidas = [];
        $paginaNueva = self::moverFirmaVisual($fuente, $mapa, $paginaNueva, $paginaId, $paginaNuevaId, $objetos, $siguiente, $firmasMovidas);
        $objetos[$paginaNuevaId] = $paginaNueva;

        $catalogoId = $siguiente;
        $siguiente++;
        $catalogo = self::diccionario($fuente, $mapa, $raiz);
        $catalogo = self::reemplazarReferencia($catalogo, 'Pages', $paginasNuevasId);
        $catalogo = self::moverCamposDeFirma($fuente, $mapa, $catalogo, $firmasMovidas, $objetos, $siguiente);
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
     * La firma hecha en el visor es una anotación. Al crear la página nueva hay que
     * apuntar esa firma a la página nueva, si no el visor deja de pintarla.
     *
     * @param  array<int, int>  $mapa
     * @param  array<int, string>  $objetos
     * @param  array<int, int>  $firmasMovidas
     */
    private static function moverFirmaVisual(
        string $pdf,
        array $mapa,
        string $pagina,
        int $paginaVieja,
        int $paginaNueva,
        array &$objetos,
        int &$siguiente,
        array &$firmasMovidas,
    ): string {
        if (! preg_match('/\/Annots\s*\[([^\]]*)\]/', $pagina, $lista)) {
            return $pagina;
        }
        if (! preg_match_all('/(\d+)\s+0\s+R/', $lista[1], $ids)) {
            return $pagina;
        }

        $nuevaLista = $lista[1];
        foreach ($ids[1] as $idTexto) {
            $id = (int) $idTexto;
            try {
                $anotacion = self::diccionario($pdf, $mapa, $id);
            } catch (\Throwable) {
                continue;
            }
            if (! preg_match('/\/P\s+'.$paginaVieja.'\s+0\s+R/', $anotacion)) {
                continue;
            }
            $copia = preg_replace(
                '/\/P\s+'.$paginaVieja.'\s+0\s+R/',
                '/P '.$paginaNueva.' 0 R',
                $anotacion,
                1,
            );
            if (! is_string($copia)) {
                continue;
            }
            $nuevoId = $siguiente;
            $siguiente++;
            $objetos[$nuevoId] = $copia;
            $firmasMovidas[$id] = $nuevoId;
            $actualizada = preg_replace(
                '/(?<!\d)'.$id.'\s+0\s+R/',
                $nuevoId.' 0 R',
                $nuevaLista,
                1,
            );
            if (is_string($actualizada)) {
                $nuevaLista = $actualizada;
            }
        }

        return str_replace($lista[0], '/Annots['.$nuevaLista.']', $pagina);
    }

    /**
     * @param  array<int, int>  $mapa
     * @param  array<int, int>  $firmasMovidas
     * @param  array<int, string>  $objetos
     */
    private static function moverCamposDeFirma(
        string $pdf,
        array $mapa,
        string $catalogo,
        array $firmasMovidas,
        array &$objetos,
        int &$siguiente,
    ): string {
        if ($firmasMovidas === []) {
            return $catalogo;
        }
        $acroId = self::referencia($catalogo, 'AcroForm');
        if ($acroId === null) {
            return $catalogo;
        }

        try {
            $acro = self::diccionario($pdf, $mapa, $acroId);
        } catch (\Throwable) {
            return $catalogo;
        }

        foreach ($firmasMovidas as $viejo => $nuevo) {
            $actualizado = preg_replace(
                '/(?<!\d)'.$viejo.'\s+0\s+R/',
                $nuevo.' 0 R',
                $acro,
            );
            if (is_string($actualizado)) {
                $acro = $actualizado;
            }
        }

        $nuevoAcro = $siguiente;
        $siguiente++;
        $objetos[$nuevoAcro] = $acro;

        return self::reemplazarReferencia($catalogo, 'AcroForm', $nuevoAcro);
    }

    /**
     * @return array{0: array<int, int>, 1: string, 2: string}
     */
    private static function mapaYTrailer(string $pdf): array
    {
        $inicio = self::startxref($pdf);
        $mapa = [];
        /** @var array<int, array{stm: int, index: int}> $comprimidos */
        $comprimidos = [];
        $definidos = [];
        $trailer = '';
        $vistos = [];

        while ($inicio !== null && ! isset($vistos[$inicio])) {
            $vistos[$inicio] = true;
            if (substr($pdf, $inicio, 4) === 'xref') {
                [$este, $inicio] = self::leerXrefClasico($pdf, $inicio, $mapa, $comprimidos, $definidos);
            } else {
                [$este, $inicio] = self::leerXrefStream($pdf, $inicio, $mapa, $comprimidos, $definidos);
            }
            if ($trailer === '') {
                $trailer = $este;
            }
        }

        if (($mapa === [] && $comprimidos === []) || $trailer === '') {
            throw new \RuntimeException('No se pudo leer la estructura del PDF firmado.');
        }
        if (self::referencia($trailer, 'Encrypt') !== null) {
            throw new \RuntimeException('El PDF está cifrado.');
        }

        self::materializarObjetos($pdf, $mapa, $comprimidos);

        return [$mapa, $trailer, $pdf];
    }

    /**
     * @param  array<int, int>  $mapa
     * @param  array<int, array{stm: int, index: int}>  $comprimidos
     * @param  array<int, true>  $definidos
     * @return array{0: string, 1: ?int}
     */
    private static function leerXrefClasico(string $pdf, int $inicio, array &$mapa, array &$comprimidos, array &$definidos): array
    {
        $cursor = self::saltarEspacio($pdf, $inicio + 4);

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
                if (! isset($definidos[$numero])) {
                    $definidos[$numero] = true;
                    if ($entrada[3] === 'n') {
                        $mapa[$numero] = (int) $entrada[1];
                        unset($comprimidos[$numero]);
                    }
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
        $este = substr($pdf, $trailerPos + 7, ($fin === false ? 800 : $fin - $trailerPos - 7));

        return [$este, self::referenciaNumero($este, 'Prev')];
    }

    /**
     * @param  array<int, int>  $mapa
     * @param  array<int, array{stm: int, index: int}>  $comprimidos
     * @param  array<int, true>  $definidos
     * @return array{0: string, 1: ?int}
     */
    private static function leerXrefStream(string $pdf, int $inicio, array &$mapa, array &$comprimidos, array &$definidos): array
    {
        [$dict, $despues] = self::objetoDesde($pdf, $inicio);
        $anchos = self::anchosW($dict);
        $ancho = array_sum($anchos);
        if ($ancho < 1) {
            throw new \RuntimeException('La tabla de referencias del PDF no es legible.');
        }
        $bytes = self::decodificarStream(self::bytesStream($pdf, $despues, $dict, $mapa), $dict, $ancho);

        $cursor = 0;
        foreach (self::indiceXref($dict) as [$primero, $cantidad]) {
            for ($i = 0; $i < $cantidad; $i++) {
                $fila = substr($bytes, $cursor, $ancho);
                $cursor += $ancho;
                if (strlen($fila) < $ancho) {
                    throw new \RuntimeException('La tabla de referencias del PDF está incompleta.');
                }
                $numero = $primero + $i;
                if (isset($definidos[$numero])) {
                    continue;
                }
                $definidos[$numero] = true;
                $tipo = $anchos[0] === 0 ? 1 : self::entero($fila, 0, $anchos[0]);
                $campo = self::entero($fila, $anchos[0], $anchos[1]);
                $indice = self::entero($fila, $anchos[0] + $anchos[1], $anchos[2]);
                if ($tipo === 1) {
                    $mapa[$numero] = $campo;
                    unset($comprimidos[$numero]);
                } elseif ($tipo === 2) {
                    unset($mapa[$numero]);
                    $comprimidos[$numero] = ['stm' => $campo, 'index' => $indice];
                }
            }
        }

        return [$dict, self::referenciaNumero($dict, 'Prev')];
    }

    /**
     * @param  array<int, int>  $mapa
     * @param  array<int, array{stm: int, index: int}>  $comprimidos
     */
    private static function materializarObjetos(string &$pdf, array &$mapa, array $comprimidos): void
    {
        $cache = [];
        foreach ($comprimidos as $id => $info) {
            $stm = $info['stm'];
            if (! isset($cache[$stm])) {
                $cache[$stm] = self::objetosDeStream($pdf, $mapa, $stm);
            }
            $contenido = $cache[$stm][$info['index']] ?? null;
            if (! is_string($contenido) || $contenido === '') {
                throw new \RuntimeException('Falta un objeto comprimido del PDF.');
            }
            if (! str_ends_with($pdf, "\n")) {
                $pdf .= "\n";
            }
            $offset = strlen($pdf);
            $pdf .= $id." 0 obj\n".$contenido."\nendobj\n";
            $mapa[$id] = $offset;
        }
    }

    /**
     * @param  array<int, int>  $mapa
     * @return array<int, string>
     */
    private static function objetosDeStream(string $pdf, array $mapa, int $stmId): array
    {
        $offset = $mapa[$stmId] ?? null;
        if ($offset === null) {
            throw new \RuntimeException('Falta el contenedor de objetos del PDF.');
        }
        [$dict, $despues] = self::objetoDesde($pdf, $offset);
        $raw = self::decodificarStream(self::bytesStream($pdf, $despues, $dict, $mapa), $dict);
        $cantidad = self::referenciaNumero($dict, 'N');
        $primero = self::referenciaNumero($dict, 'First');
        if ($cantidad === null || $primero === null) {
            throw new \RuntimeException('El contenedor de objetos del PDF está incompleto.');
        }
        $cabecera = substr($raw, 0, $primero);
        if (! preg_match_all('/(\d+)\s+(\d+)/', $cabecera, $pares, PREG_SET_ORDER)) {
            throw new \RuntimeException('El contenedor de objetos del PDF no es legible.');
        }
        $pares = array_slice($pares, 0, $cantidad);
        $objetos = [];
        foreach ($pares as $indice => $par) {
            $desde = $primero + (int) $par[2];
            $hasta = isset($pares[$indice + 1]) ? $primero + (int) $pares[$indice + 1][2] : strlen($raw);
            $objetos[$indice] = trim(substr($raw, $desde, max(0, $hasta - $desde)));
        }

        return $objetos;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function objetoDesde(string $pdf, int $offset): array
    {
        if (! preg_match('/^\d+\s+\d+\s+obj\s*/', substr($pdf, $offset, 64), $cabecera)) {
            throw new \RuntimeException('Objeto PDF ilegible.');
        }
        $desde = $offset + strlen($cabecera[0]);
        $inicio = strpos($pdf, '<<', $desde);
        if ($inicio === false || $inicio > $desde + 64) {
            throw new \RuntimeException('El objeto PDF no es un diccionario.');
        }
        $dict = self::extraerDiccionario($pdf, $inicio);

        return [$dict, $inicio + strlen($dict)];
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function bytesStream(string $pdf, int $despues, string $dict, array $mapa): string
    {
        $cursor = self::saltarEspacio($pdf, $despues);
        if (substr($pdf, $cursor, 6) !== 'stream') {
            throw new \RuntimeException('El objeto PDF no tiene stream.');
        }
        $cursor += 6;
        if (($pdf[$cursor] ?? '') === "\r") {
            $cursor++;
        }
        if (($pdf[$cursor] ?? '') === "\n") {
            $cursor++;
        }
        $largo = self::longitudStream($dict, $pdf, $mapa);
        $bytes = substr($pdf, $cursor, $largo);
        if (strlen($bytes) !== $largo) {
            throw new \RuntimeException('Stream PDF incompleto.');
        }

        return $bytes;
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function longitudStream(string $dict, string $pdf, array $mapa): int
    {
        if (preg_match('/\/Length\s+(\d+)(?!\s+0\s+R)/', $dict, $directo)) {
            return (int) $directo[1];
        }
        $id = self::referencia($dict, 'Length');
        $offset = $id !== null ? ($mapa[$id] ?? null) : null;
        if ($offset === null && $id !== null && preg_match('/(?<!\d)'.$id.'\s+0\s+obj\s+(\d+)/', $pdf, $numero)) {
            return (int) $numero[1];
        }
        if ($offset === null || ! preg_match('/obj\s+([0-9]+)/', substr($pdf, $offset, 80), $numero)) {
            throw new \RuntimeException('No se pudo leer el tamaño de un stream del PDF.');
        }

        return (int) $numero[1];
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function decodificarStream(string $bytes, string $dict, int $columnasPorDefecto = 1): string
    {
        foreach (self::filtros($dict) as $filtro) {
            if ($filtro === 'FlateDecode') {
                $bytes = self::inflar($bytes);
                [$predictor, $columnas, $colores, $bits] = self::parametrosPredictor($dict, $columnasPorDefecto);
                if ($predictor > 1) {
                    $bytes = self::quitarPredictor($bytes, $predictor, $columnas, $colores, $bits);
                }
            } elseif ($filtro === 'ASCIIHexDecode') {
                $bytes = self::asciiHex($bytes);
            } else {
                throw new \RuntimeException('El PDF usa una compresión no soportada.');
            }
        }

        return $bytes;
    }

    /**
     * @return list<string>
     */
    private static function filtros(string $dict): array
    {
        if (preg_match('/\/Filter\s*\[([^\]]*)\]/', $dict, $lista)) {
            preg_match_all('/\/([A-Za-z0-9]+)/', $lista[1], $nombres);

            return $nombres[1];
        }
        if (preg_match('/\/Filter\s*\/([A-Za-z0-9]+)/', $dict, $uno)) {
            return [$uno[1]];
        }

        return [];
    }

    private static function inflar(string $bytes): string
    {
        $salida = @gzuncompress($bytes);
        if (is_string($salida)) {
            return $salida;
        }
        $salida = @gzinflate($bytes);
        if (is_string($salida)) {
            return $salida;
        }

        throw new \RuntimeException('No se pudo descomprimir el PDF.');
    }

    private static function asciiHex(string $bytes): string
    {
        $hex = preg_replace('/\s+|[^0-9A-Fa-f>].*$/s', '', $bytes) ?? '';
        $hex = rtrim($hex, '>');
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }
        $binario = hex2bin($hex);
        if ($binario === false) {
            throw new \RuntimeException('No se pudo leer un stream del PDF.');
        }

        return $binario;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function anchosW(string $dict): array
    {
        if (! preg_match('/\/W\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s*\]/', $dict, $anchos)) {
            throw new \RuntimeException('La tabla de referencias del PDF no tiene anchos.');
        }

        return [(int) $anchos[1], (int) $anchos[2], (int) $anchos[3]];
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private static function indiceXref(string $dict): array
    {
        if (preg_match('/\/Index\s*\[([^\]]*)\]/', $dict, $indice)) {
            preg_match_all('/(\d+)\s+(\d+)/', $indice[1], $pares, PREG_SET_ORDER);
            $secciones = [];
            foreach ($pares as $par) {
                $secciones[] = [(int) $par[1], (int) $par[2]];
            }
            if ($secciones !== []) {
                return $secciones;
            }
        }
        $size = self::referenciaNumero($dict, 'Size') ?? 0;

        return [[0, $size]];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private static function parametrosPredictor(string $dict, int $columnasPorDefecto): array
    {
        $parms = '';
        if (preg_match('/\/DecodeParms\s*<<.*?>>/s', $dict, $coincidencia)) {
            $parms = $coincidencia[0];
        }
        $predictor = self::referenciaNumero($parms, 'Predictor') ?? 1;
        $columnas = self::referenciaNumero($parms, 'Columns') ?? $columnasPorDefecto;
        $colores = self::referenciaNumero($parms, 'Colors') ?? 1;
        $bits = self::referenciaNumero($parms, 'BitsPerComponent') ?? 8;

        return [$predictor, max(1, $columnas), max(1, $colores), max(1, $bits)];
    }

    private static function quitarPredictor(string $bytes, int $predictor, int $columnas, int $colores, int $bits): string
    {
        if ($predictor <= 1) {
            return $bytes;
        }
        if ($predictor < 10) {
            throw new \RuntimeException('El PDF usa un predictor no soportado.');
        }

        $fila = (int) ceil($columnas * $colores * $bits / 8);
        $salida = '';
        $anterior = str_repeat("\0", $fila);
        $cursor = 0;
        $largo = strlen($bytes);
        while ($cursor < $largo) {
            $filtro = ord($bytes[$cursor]);
            $cursor++;
            $row = substr($bytes, $cursor, $fila);
            $cursor += strlen($row);
            if (strlen($row) < $fila) {
                $row = str_pad($row, $fila, "\0");
            }
            $decodificada = '';
            for ($columna = 0; $columna < $fila; $columna++) {
                $valor = ord($row[$columna]);
                $izquierda = $columna > 0 ? ord($decodificada[$columna - 1]) : 0;
                $arriba = ord($anterior[$columna]);
                $arribaIzquierda = $columna > 0 ? ord($anterior[$columna - 1]) : 0;
                $decodificada .= chr(match ($filtro) {
                    1 => ($valor + $izquierda) & 255,
                    2 => ($valor + $arriba) & 255,
                    3 => ($valor + intdiv($izquierda + $arriba, 2)) & 255,
                    4 => ($valor + self::paeth($izquierda, $arriba, $arribaIzquierda)) & 255,
                    default => $valor,
                });
            }
            $salida .= $decodificada;
            $anterior = $decodificada;
        }

        return $salida;
    }

    private static function paeth(int $izquierda, int $arriba, int $arribaIzquierda): int
    {
        $estimado = $izquierda + $arriba - $arribaIzquierda;
        $diferenciaIzquierda = abs($estimado - $izquierda);
        $diferenciaArriba = abs($estimado - $arriba);
        $diferenciaDiagonal = abs($estimado - $arribaIzquierda);
        if ($diferenciaIzquierda <= $diferenciaArriba && $diferenciaIzquierda <= $diferenciaDiagonal) {
            return $izquierda;
        }
        if ($diferenciaArriba <= $diferenciaDiagonal) {
            return $arriba;
        }

        return $arribaIzquierda;
    }

    private static function entero(string $fila, int $desde, int $ancho): int
    {
        if ($ancho === 0) {
            return 0;
        }
        $valor = 0;
        for ($i = 0; $i < $ancho; $i++) {
            $valor = ($valor << 8) + ord($fila[$desde + $i] ?? "\0");
        }

        return $valor;
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
            if (preg_match('/\/Type\s*\/Pages\b/', $dict)) {
                if (! preg_match('/\/Kids\s*\[(.*?)\]/s', $dict, $hijos) || ! preg_match('/(\d+)\s+0\s+R/', $hijos[1], $primero)) {
                    throw new \RuntimeException('El PDF no tiene páginas legibles.');
                }
                $nodo = (int) $primero[1];

                continue;
            }
            if (preg_match('/\/Type\s*\/Page\b/', $dict)) {
                return $nodo;
            }

            throw new \RuntimeException('No se encontró la primera página del PDF.');
        }

        throw new \RuntimeException('El árbol de páginas del PDF está en bucle.');
    }

    /**
     * @param  array<int, int>  $mapa
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function mediaBoxResuelto(string $pdf, array $mapa, string $pagina): array
    {
        $vistos = [];
        $actual = $pagina;
        while (true) {
            if (preg_match('/\/MediaBox\s*\[\s*([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s+([0-9.\-]+)\s*\]/', $actual, $caja)) {
                return [(float) $caja[1], (float) $caja[2], (float) $caja[3], (float) $caja[4]];
            }
            $padre = self::referencia($actual, 'Parent');
            if ($padre === null || isset($vistos[$padre])) {
                return [0.0, 0.0, 595.276, 841.890];
            }
            $vistos[$padre] = true;
            $actual = self::diccionario($pdf, $mapa, $padre);
        }
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function asegurarContenido(string $pdf, array $mapa, string $pagina): string
    {
        if (preg_match('/\/Contents\b/', $pagina)) {
            return $pagina;
        }

        $vistos = [];
        $padre = self::referencia($pagina, 'Parent');
        while ($padre !== null && ! isset($vistos[$padre])) {
            $vistos[$padre] = true;
            $dict = self::diccionario($pdf, $mapa, $padre);
            if (preg_match('/\/Contents\s+(\d+\s+0\s+R|\[[^\]]*\])/', $dict, $contenido)) {
                return self::insertarAntesDelCierre($pagina, '/Contents '.$contenido[1]);
            }
            $padre = self::referencia($dict, 'Parent');
        }

        return $pagina;
    }

    /**
     * @param  array<int, int>  $mapa
     */
    private static function recursosConSello(string $pdf, array $mapa, string $pagina, int $imagenId): string
    {
        $recursos = self::diccionarioRecursos($pdf, $mapa, $pagina);

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

    /**
     * @param  array<int, int>  $mapa
     */
    private static function diccionarioRecursos(string $pdf, array $mapa, string $pagina): string
    {
        $vistos = [];
        $actual = $pagina;
        while (true) {
            if (preg_match('/\/Resources\s+(\d+)\s+0\s+R/', $actual, $referencia)) {
                return self::diccionario($pdf, $mapa, (int) $referencia[1]);
            }
            if (preg_match('/\/Resources\s*(<<)/', $actual, $inline, PREG_OFFSET_CAPTURE)) {
                return self::extraerDiccionario($actual, (int) $inline[1][1]);
            }
            $padre = self::referencia($actual, 'Parent');
            if ($padre === null || isset($vistos[$padre])) {
                return '<< >>';
            }
            $vistos[$padre] = true;
            $actual = self::diccionario($pdf, $mapa, $padre);
        }
    }

    private static function ponerReferencia(string $dict, string $clave, int $id): string
    {
        return self::insertarAntesDelCierre(self::quitarClave($dict, $clave), '/'.$clave.' '.$id.' 0 R');
    }

    private static function insertarAntesDelCierre(string $dict, string $fragmento): string
    {
        $cierre = strrpos($dict, '>>');
        if ($cierre === false) {
            throw new \RuntimeException('No se pudo actualizar el PDF firmado.');
        }

        return substr($dict, 0, $cierre).$fragmento.' >>';
    }

    private static function quitarClave(string $dict, string $clave): string
    {
        $patron = '/\/'.preg_quote($clave, '/').'(?![A-Za-z0-9])/';
        if (! preg_match($patron, $dict, $coincidencia, PREG_OFFSET_CAPTURE)) {
            return $dict;
        }
        $inicio = (int) $coincidencia[0][1];
        $cursor = self::saltarEspacio($dict, $inicio + strlen($coincidencia[0][0]));
        $fin = self::finDeValor($dict, $cursor);
        $resto = substr($dict, $fin);
        if ($resto !== '' && ! preg_match('/^\s/', $resto)) {
            $resto = ' '.$resto;
        }

        return substr($dict, 0, $inicio).ltrim($resto);
    }

    private static function finDeValor(string $pdf, int $cursor): int
    {
        $cursor = self::saltarEspacio($pdf, $cursor);
        $caracter = $pdf[$cursor] ?? '';
        if ($caracter === '<' && ($pdf[$cursor + 1] ?? '') === '<') {
            return $cursor + strlen(self::extraerDiccionario($pdf, $cursor));
        }
        if ($caracter === '[') {
            return self::finDeCorchete($pdf, $cursor);
        }
        if ($caracter === '(') {
            return self::finDeLiteral($pdf, $cursor);
        }
        if ($caracter === '<') {
            $fin = strpos($pdf, '>', $cursor);

            return $fin === false ? strlen($pdf) : $fin + 1;
        }
        if ($caracter === '/' && preg_match('/\G\/[^\s\/\[\]<>()]+/', $pdf, $nombre, 0, $cursor)) {
            return $cursor + strlen($nombre[0]);
        }
        if (preg_match('/\G\d+\s+\d+\s+R\b/', $pdf, $referencia, 0, $cursor)) {
            return $cursor + strlen($referencia[0]);
        }
        if (preg_match('/\G[+-]?(?:\d+\.\d+|\d+)/', $pdf, $numero, 0, $cursor)) {
            return $cursor + strlen($numero[0]);
        }

        throw new \RuntimeException('No se pudo leer un valor del PDF.');
    }

    private static function finDeCorchete(string $pdf, int $inicio): int
    {
        $profundidad = 0;
        $i = $inicio;
        $largo = strlen($pdf);
        while ($i < $largo) {
            $caracter = $pdf[$i];
            if ($caracter === '[') {
                $profundidad++;
                $i++;

                continue;
            }
            if ($caracter === ']') {
                $profundidad--;
                $i++;
                if ($profundidad === 0) {
                    return $i;
                }

                continue;
            }
            if ($caracter === '<' && ($pdf[$i + 1] ?? '') === '<') {
                $i += strlen(self::extraerDiccionario($pdf, $i));

                continue;
            }
            if ($caracter === '(') {
                $i = self::finDeLiteral($pdf, $i);

                continue;
            }
            $i++;
        }

        throw new \RuntimeException('Arreglo PDF incompleto.');
    }

    private static function finDeLiteral(string $pdf, int $inicio): int
    {
        $i = $inicio + 1;
        $largo = strlen($pdf);
        while ($i < $largo) {
            if ($pdf[$i] === '\\') {
                $i += 2;

                continue;
            }
            if ($pdf[$i] === ')') {
                return $i + 1;
            }
            $i++;
        }

        throw new \RuntimeException('Texto PDF incompleto.');
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

        return '<< /Length '.strlen($contenido)." >>\nstream\n".$contenido.'endstream';
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
