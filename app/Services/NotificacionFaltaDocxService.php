<?php

namespace App\Services;

use App\Models\NotificacionFalta;
use App\Support\NotificacionFaltaMarcacion;
use Illuminate\Support\Facades\File;
use ZipArchive;

class NotificacionFaltaDocxService
{
    public function __construct(private readonly NotificacionFaltaService $datos) {}

    public function plantillaPath(): ?string
    {
        $path = resource_path('templates/oficios/notificacion-faltas-th.docx');

        return is_readable($path) ? $path : null;
    }

    public function generar(NotificacionFalta $notificacion): string
    {
        $plantilla = $this->plantillaPath();
        if ($plantilla === null) {
            throw new \InvalidArgumentException('No hay plantilla DOCX para notificaciones de faltas.');
        }

        $notificacion->loadMissing(['destinatario', 'decano']);
        $valores = $this->placeholders($notificacion);
        $correoDecano = (string) ($valores['_correo_decano'] ?? '');
        unset($valores['_correo_decano']);

        $tempIn = tempnam(sys_get_temp_dir(), 'notif-src-');
        $tempOut = tempnam(sys_get_temp_dir(), 'notif-out-');
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

        for ($i = 0; $i < $source->numFiles; $i++) {
            $name = $source->getNameIndex($i);
            $content = $source->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            if (is_string($name) && str_ends_with(strtolower($name), '.xml')) {
                $xml = $this->rellenarXml($content, $valores, $correoDecano);
                $zip->addFromString($name, $xml);
            } else {
                $zip->addFromString($name, $content);
            }
        }

        $source->close();
        $zip->close();
        @unlink($tempIn);

        $binary = file_get_contents($tempOut) ?: '';
        @unlink($tempOut);

        return $binary;
    }

    /**
     * @return array<string, string>
     */
    public function placeholders(NotificacionFalta $notificacion): array
    {
        $destinatario = $notificacion->destinatario;
        $decano = $notificacion->decano;

        return [
            '[Código del oficio]' => $notificacion->codigo_oficio,
            '[Nombre completo del colaborador]' => $destinatario->nombreCompleto(),
            '[Cargo del colaborador]' => $this->datos->cargoColaborador($destinatario),
            '[Carrera o unidad]' => $this->datos->carreraOUnidad($destinatario),
            '[Fecha de la falta]' => $notificacion->fecha_falta->locale('es')->translatedFormat('j \d\e F \d\e Y'),
            '[Tipo de marcación faltante]' => NotificacionFaltaMarcacion::enOracion($notificacion->tipo_marcacion),
            '[Nombre del decano]' => $decano->nombreCompleto(),
            '[Título del firmante]' => $notificacion->emitido_delegado ? 'Decano (s)' : 'Decano',
            '[Fecha automática del sistema]' => now()->locale('es')->translatedFormat('d \d\e F \d\e Y'),
            '_correo_decano' => $decano->email ?? '—',
        ];
    }

    /**
     * @param  array<string, string>  $valores
     */
    private function rellenarXml(string $xml, array $valores, string $correoDecano): string
    {
        $xml = preg_replace(
            '/Correo Institucional: \[Correo\]/u',
            'Correo Institucional: '.$this->xmlEscape($correoDecano),
            $xml,
            1,
        ) ?? $xml;

        $codigo = $valores['[Código del oficio]'] ?? '';
        if ($codigo !== '') {
            $xml = str_replace('[Código del oficio]', $codigo, $xml);
            $xml = preg_replace('/FACIVITEC-ADM-\d+/u', $codigo, $xml, 1) ?? $xml;
        }

        uksort($valores, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        $buscar = array_keys($valores);
        $reemplazar = array_map(fn (string $v): string => $this->xmlEscape($v), array_values($valores));

        return str_replace($buscar, $reemplazar, $xml);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
