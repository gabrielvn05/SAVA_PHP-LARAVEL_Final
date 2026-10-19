<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Support\MatrizNovedades;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use ZipArchive;

class MatrizNovedadesXlsxWriter
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const FILA_DATOS = 7;

    private const FILA_PLANTILLA = 12;

    private const FILA_PIE = 29;

    public function plantillaPath(): string
    {
        return resource_path('templates/reportes/phm-08-f-002-matriz-novedades.xlsx');
    }

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    public function generar(Collection $solicitudes, ?CarbonInterface $desde, ?CarbonInterface $hasta): string
    {
        $plantilla = $this->plantillaPath();
        if (! is_file($plantilla)) {
            abort(500, 'No está la plantilla de la matriz de novedades.');
        }

        $destino = tempnam(sys_get_temp_dir(), 'matriz-novedades-');
        if ($destino === false) {
            abort(500, 'No se pudo generar el archivo Excel.');
        }
        $path = $destino.'.xlsx';
        @unlink($destino);

        $origen = new ZipArchive;
        if ($origen->open($plantilla) !== true) {
            abort(500, 'No se pudo abrir la plantilla de la matriz de novedades.');
        }

        $salida = new ZipArchive;
        if ($salida->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $origen->close();
            abort(500, 'No se pudo generar el archivo Excel.');
        }

        $periodo = $this->periodoDe($solicitudes, $desde, $hasta);

        for ($i = 0; $i < $origen->numFiles; $i++) {
            $nombre = $origen->getNameIndex($i);
            if (! is_string($nombre) || str_ends_with($nombre, '/')) {
                continue;
            }

            $contenido = $origen->getFromIndex($i);
            if (! is_string($contenido)) {
                continue;
            }

            if ($nombre === 'xl/worksheets/sheet2.xml') {
                $contenido = $this->llenarHoja($contenido, $solicitudes);
            } elseif ($nombre === 'xl/sharedStrings.xml') {
                $contenido = $this->reemplazarTextos($contenido, [
                    'UBICAR EL NOMBRE DE LA UNIDAD' => MatrizNovedades::unidad(),
                    'PERIODO : DEL XX AL XX DE XXXX DE 202X' => $periodo,
                ]);
            }

            $salida->addFromString($nombre, $contenido);
        }

        $origen->close();
        $salida->close();

        return $path;
    }

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    private function periodoDe(Collection $solicitudes, ?CarbonInterface $desde, ?CarbonInterface $hasta): string
    {
        if ($desde !== null && $hasta !== null) {
            return MatrizNovedades::periodo($desde, $hasta);
        }

        if ($solicitudes->isEmpty()) {
            return MatrizNovedades::periodo(now()->startOfMonth(), now()->endOfMonth());
        }

        $inicio = $solicitudes->min(fn (Solicitud $solicitud) => $solicitud->fecha_inicio->copy()->startOfDay());
        $fin = $solicitudes->max(fn (Solicitud $solicitud) => $solicitud->fecha_fin->copy()->startOfDay());

        return MatrizNovedades::periodo($inicio, $fin);
    }

    /**
     * @param  Collection<int, Solicitud>  $solicitudes
     */
    private function llenarHoja(string $xml, Collection $solicitudes): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (! $dom->loadXML($xml)) {
            return $xml;
        }

        $sheetData = $dom->getElementsByTagName('sheetData')->item(0);
        if (! $sheetData instanceof DOMElement) {
            return $xml;
        }

        $filas = [];
        foreach (iterator_to_array($sheetData->childNodes) as $nodo) {
            if ($nodo instanceof DOMElement && $nodo->localName === 'row') {
                $filas[(int) $nodo->getAttribute('r')] = $nodo;
            }
        }

        $plantilla = $filas[self::FILA_PLANTILLA] ?? null;
        if (! $plantilla instanceof DOMElement) {
            return $xml;
        }

        $pie = [];
        for ($fila = self::FILA_PIE; $fila <= 34; $fila++) {
            if (isset($filas[$fila])) {
                $pie[$fila] = $filas[$fila]->cloneNode(true);
            }
        }
        $molde = $plantilla->cloneNode(true);

        foreach ($filas as $numero => $nodo) {
            if ($numero >= self::FILA_DATOS) {
                $sheetData->removeChild($nodo);
            }
        }

        $cantidad = max(1, $solicitudes->count());
        $numero = 1;
        if ($solicitudes->isEmpty()) {
            $sheetData->appendChild($this->renumerarFila($molde, self::FILA_DATOS));
        } else {
            foreach ($solicitudes as $solicitud) {
                $fila = $this->renumerarFila($molde->cloneNode(true), self::FILA_DATOS + $numero - 1);
                $this->escribirDatos($dom, $fila, $solicitud, $numero);
                $sheetData->appendChild($fila);
                $numero++;
            }
        }

        $inicioPie = self::FILA_DATOS + $cantidad + 1;
        $desplazamiento = $inicioPie - self::FILA_PIE;
        foreach ($pie as $nodo) {
            if (! $nodo instanceof DOMElement) {
                continue;
            }
            $sheetData->appendChild($this->renumerarFila($nodo, (int) $nodo->getAttribute('r') + $desplazamiento));
        }

        $this->desplazarReferencias($dom, $desplazamiento);
        $ultima = $inicioPie + 5;
        $dimension = $dom->getElementsByTagName('dimension')->item(0);
        if ($dimension instanceof DOMElement) {
            $dimension->setAttribute('ref', 'A1:V'.$ultima);
        }

        return $dom->saveXML() ?: $xml;
    }

    private function renumerarFila(DOMElement $fila, int $nueva): DOMElement
    {
        $fila->setAttribute('r', (string) $nueva);
        foreach ($fila->getElementsByTagName('c') as $celda) {
            if (! $celda instanceof DOMElement) {
                continue;
            }
            $ref = $celda->getAttribute('r');
            $columna = preg_replace('/\d+$/', '', $ref) ?: 'A';
            $celda->setAttribute('r', $columna.$nueva);
        }

        return $fila;
    }

    private function escribirDatos(DOMDocument $dom, DOMElement $fila, Solicitud $solicitud, int $numero): void
    {
        $datos = MatrizNovedades::fila($solicitud);
        $this->ponerNumero($dom, $fila, 'A', $numero);
        $this->ponerTexto($dom, $fila, 'B', $datos['cedula']);
        $this->ponerTexto($dom, $fila, 'C', $datos['nombre']);
        $this->ponerTexto($dom, $fila, 'D', $datos['regimen']);
        $this->ponerTexto($dom, $fila, 'E', $datos['puesto']);
        $this->ponerNumero($dom, $fila, 'F', $datos['dias']);
        $this->ponerNumero($dom, $fila, 'G', $datos['horas']);
        $this->ponerNumero($dom, $fila, 'H', $datos['minutos']);

        foreach (['I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'] as $columna) {
            $this->ponerTexto($dom, $fila, $columna, $datos['novedad'] === $columna ? 'X' : '');
        }

        $this->ponerTexto($dom, $fila, 'S', $datos['desde']);
        $this->ponerTexto($dom, $fila, 'T', $datos['hasta']);
        $this->ponerTexto($dom, $fila, 'U', $datos['documento']);
        $this->ponerTexto($dom, $fila, 'V', '');
        $this->estiloNumero($fila, 'A', '52');
        $this->estiloNumero($fila, 'G', '18');
        $this->estiloNumero($fila, 'H', '18');
    }

    private function ponerTexto(DOMDocument $dom, DOMElement $fila, string $columna, string $texto): void
    {
        $celda = $this->celda($fila, $columna);
        if (! $celda instanceof DOMElement) {
            return;
        }

        $this->vaciarCelda($celda);
        if ($texto === '') {
            $celda->removeAttribute('t');

            return;
        }

        $celda->setAttribute('t', 'inlineStr');
        $is = $dom->createElementNS(self::NS, 'is');
        $t = $dom->createElementNS(self::NS, 't');
        if (preg_match('/^\s|\s$/', $texto) === 1) {
            $t->setAttribute('xml:space', 'preserve');
        }
        $t->appendChild($dom->createTextNode($texto));
        $is->appendChild($t);
        $celda->appendChild($is);
    }

    private function ponerNumero(DOMDocument $dom, DOMElement $fila, string $columna, int $valor): void
    {
        $celda = $this->celda($fila, $columna);
        if (! $celda instanceof DOMElement) {
            return;
        }

        $this->vaciarCelda($celda);
        $celda->removeAttribute('t');
        $v = $dom->createElementNS(self::NS, 'v', (string) $valor);
        $celda->appendChild($v);
    }

    private function estiloNumero(DOMElement $fila, string $columna, string $estilo): void
    {
        $celda = $this->celda($fila, $columna);
        if ($celda instanceof DOMElement) {
            $celda->setAttribute('s', $estilo);
        }
    }

    private function celda(DOMElement $fila, string $columna): ?DOMElement
    {
        $filaNumero = $fila->getAttribute('r');
        foreach ($fila->getElementsByTagName('c') as $celda) {
            if ($celda instanceof DOMElement && $celda->getAttribute('r') === $columna.$filaNumero) {
                return $celda;
            }
        }

        return null;
    }

    private function vaciarCelda(DOMElement $celda): void
    {
        while ($celda->firstChild !== null) {
            $celda->removeChild($celda->firstChild);
        }
    }

    private function desplazarReferencias(DOMDocument $dom, int $desplazamiento): void
    {
        if ($desplazamiento === 0) {
            return;
        }

        foreach ($dom->getElementsByTagName('mergeCell') as $merge) {
            if (! $merge instanceof DOMElement) {
                continue;
            }
            $ref = $merge->getAttribute('ref');
            if (! preg_match('/^[A-Z]+(\d+):[A-Z]+(\d+)$/', $ref, $coincide)) {
                continue;
            }
            if ((int) $coincide[1] < self::FILA_PIE && (int) $coincide[2] < self::FILA_PIE) {
                continue;
            }
            $merge->setAttribute('ref', preg_replace_callback(
                '/([A-Z]+)(\d+)/',
                static fn (array $parte): string => $parte[1].((int) $parte[2] + $desplazamiento),
                $ref,
            ) ?? $ref);
        }
    }

    /**
     * @param  array<string, string>  $reemplazos
     */
    private function reemplazarTextos(string $xml, array $reemplazos): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        if (! $dom->loadXML($xml)) {
            return $xml;
        }

        foreach ($dom->getElementsByTagName('si') as $si) {
            if (! $si instanceof DOMElement) {
                continue;
            }
            $texto = '';
            foreach ($si->getElementsByTagName('t') as $nodo) {
                $texto .= $nodo->textContent;
            }
            $clave = array_key_exists($texto, $reemplazos) ? $texto : trim($texto);
            if (! array_key_exists($clave, $reemplazos)) {
                continue;
            }
            $reemplazo = $reemplazos[$clave];
            while ($si->firstChild !== null) {
                $si->removeChild($si->firstChild);
            }
            $t = $dom->createElementNS(self::NS, 't');
            $t->appendChild($dom->createTextNode($reemplazo));
            $si->appendChild($t);
        }

        return $dom->saveXML() ?: $xml;
    }
}
