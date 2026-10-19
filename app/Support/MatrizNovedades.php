<?php

namespace App\Support;

use App\Enums\AppRole;
use App\Enums\PermisoMotivo;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use Carbon\CarbonInterface;

class MatrizNovedades
{
    public const MINUTOS_JORNADA = 480;

    public const UNIDAD = 'FACULTAD DE CIENCIAS DE LA VIDA Y LA TECNOLOGÍA (FACIVITEC)';

    /**
     * @return array{
     *     cedula: string,
     *     nombre: string,
     *     regimen: string,
     *     puesto: string,
     *     dias: int,
     *     horas: int,
     *     minutos: int,
     *     novedad: ?string,
     *     desde: string,
     *     hasta: string,
     *     documento: string
     * }
     */
    public static function fila(Solicitud $solicitud): array
    {
        $creador = $solicitud->creador;
        $tiempo = self::descomponer(self::minutosTomados($solicitud));

        return [
            'cedula' => (string) ($creador?->cedula ?? ''),
            'nombre' => self::apellidosYNombres($creador?->apellidos, $creador?->nombres),
            'regimen' => self::regimen($creador?->rol),
            'puesto' => self::puesto($creador?->rol),
            'dias' => $tiempo['dias'],
            'horas' => $tiempo['horas'],
            'minutos' => $tiempo['minutos'],
            'novedad' => self::columnaNovedad($solicitud),
            'desde' => $solicitud->fecha_inicio->format('d/m/Y'),
            'hasta' => $solicitud->fecha_fin->format('d/m/Y'),
            'documento' => (string) (($solicitud->detalle ?? [])['codigo_tramite'] ?? ''),
        ];
    }

    public static function apellidosYNombres(?string $apellidos, ?string $nombres): string
    {
        return trim(trim((string) $apellidos).' '.trim((string) $nombres));
    }

    public static function regimen(?AppRole $rol): string
    {
        return match ($rol) {
            AppRole::Docente, AppRole::Decano => 'Docente',
            AppRole::Mantenimiento => 'Código de trabajo',
            default => 'Administrativo',
        };
    }

    public static function puesto(?AppRole $rol): string
    {
        return match ($rol) {
            AppRole::Docente => 'docente',
            AppRole::Mantenimiento => 'mantenimiento',
            AppRole::Secretaria => 'secretaria',
            AppRole::Administrativo => 'personal tecnológico',
            AppRole::Decano => 'decano',
            default => '',
        };
    }

    /**
     * Columna de «TIPO DE NOVEDAD» que lleva la X. Vacaciones, feriados,
     * salidas injustificadas, atraso injustificado, desvinculación y falta
     * injustificada quedan vacías.
     */
    public static function columnaNovedad(Solicitud $solicitud): ?string
    {
        if ($solicitud->tipo === SolicitudTipo::Permiso) {
            return self::permisoEsLicencia($solicitud) ? 'J' : 'K';
        }

        if ($solicitud->tipo === SolicitudTipo::Justificacion) {
            return 'N';
        }

        if ($solicitud->estado === SolicitudEstado::Aprobada) {
            return 'Q';
        }

        return null;
    }

    public static function minutosTomados(Solicitud $solicitud): int
    {
        $detalle = $solicitud->detalle ?? [];

        if ($solicitud->tipo === SolicitudTipo::Permiso) {
            return self::minutosEntreHoras(
                $detalle['hora_inicio_permiso'] ?? null,
                $detalle['hora_fin_permiso'] ?? null,
            );
        }

        if ($solicitud->tipo === SolicitudTipo::Justificacion) {
            return self::minutosEntreHoras(
                $detalle['hora_llegada_establecida'] ?? null,
                $detalle['hora_llegada_real'] ?? null,
            );
        }

        $inicio = $solicitud->fecha_inicio->copy()->startOfDay();
        $fin = $solicitud->fecha_fin->copy()->startOfDay();
        $dias = (int) $inicio->diffInDays($fin) + 1;

        return max(1, $dias) * self::MINUTOS_JORNADA;
    }

    /**
     * @return array{dias: int, horas: int, minutos: int}
     */
    public static function descomponer(int $minutos): array
    {
        $minutos = max(0, $minutos);
        $dias = intdiv($minutos, self::MINUTOS_JORNADA);
        $resto = $minutos % self::MINUTOS_JORNADA;

        return [
            'dias' => $dias,
            'horas' => intdiv($resto, 60),
            'minutos' => $resto % 60,
        ];
    }

    public static function periodo(?CarbonInterface $desde, ?CarbonInterface $hasta): string
    {
        if ($desde === null || $hasta === null) {
            return 'PERIODO : DEL XX AL XX DE XXXX DE 202X';
        }

        $desde = $desde->copy()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();
        if ($hasta->lessThan($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $meses = [
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ];

        if ($desde->month === $hasta->month && $desde->year === $hasta->year) {
            return sprintf(
                'PERIODO : DEL %s AL %s DE %s DE %d',
                $desde->format('d'),
                $hasta->format('d'),
                $meses[$desde->month],
                $desde->year,
            );
        }

        return sprintf(
            'PERIODO : DEL %s DE %s DE %d AL %s DE %s DE %d',
            $desde->format('d'),
            $meses[$desde->month],
            $desde->year,
            $hasta->format('d'),
            $meses[$hasta->month],
            $hasta->year,
        );
    }

    public static function unidad(): string
    {
        return self::UNIDAD;
    }

    /**
     * @return list<string>
     */
    public static function encabezadosCsv(): array
    {
        return [
            'N°',
            'NRO. CÉDULA',
            'APELLIDOS Y NOMBRES',
            'RÉGIMEN LABORAL',
            'DENOMINACIÓN DEL PUESTO',
            'No. Dias',
            'No. Horas',
            'No. Minutos',
            'VACACIONES',
            'LICENCIA Y/O COMISION SERVICIOS CON RMU',
            'PERMISOS',
            'FERIADOS O DÍAS DE ASUETO',
            'SALIDA ANTICIPADA INJUSTIFICADA',
            'ATRASO JUSTIFICADO',
            'ATRASO INJUSTIFICADO',
            'DESVINCULACIÓN INSTITUCIONAL',
            'FALTA JUSTIFICADA',
            'FALTA INJUSTIFICADA',
            'FECHA DESDE',
            'FECHA HASTA',
            'DOCUMENTO JUSTIFICATIVO',
            'Observación',
        ];
    }

    /**
     * @return list<int|string>
     */
    public static function filaCsv(Solicitud $solicitud, int $numero): array
    {
        $fila = self::fila($solicitud);
        $marca = static fn (string $columna): string => $fila['novedad'] === $columna ? 'X' : '';

        return [
            $numero,
            $fila['cedula'],
            $fila['nombre'],
            $fila['regimen'],
            $fila['puesto'],
            $fila['dias'],
            $fila['horas'],
            $fila['minutos'],
            '',
            $marca('J'),
            $marca('K'),
            '',
            '',
            $marca('N'),
            '',
            '',
            $marca('Q'),
            '',
            $fila['desde'],
            $fila['hasta'],
            $fila['documento'],
            '',
        ];
    }

    private static function permisoEsLicencia(Solicitud $solicitud): bool
    {
        $detalle = $solicitud->detalle ?? [];
        $motivo = (string) ($detalle['motivo_permiso'] ?? '');
        if (in_array($motivo, [PermisoMotivo::Reunion->value, PermisoMotivo::PracticasVinculacion->value], true)) {
            return true;
        }

        $texto = mb_strtolower($solicitud->motivo.' '.($detalle['motivo_permiso_otro'] ?? ''), 'UTF-8');

        return str_contains($texto, 'reunión')
            || str_contains($texto, 'reunion')
            || str_contains($texto, 'práctica')
            || str_contains($texto, 'practica')
            || str_contains($texto, 'vinculación')
            || str_contains($texto, 'vinculacion');
    }

    private static function minutosEntreHoras(mixed $desde, mixed $hasta): int
    {
        $inicio = self::minutosDelDia($desde);
        $fin = self::minutosDelDia($hasta);
        if ($inicio === null || $fin === null || $fin <= $inicio) {
            return 0;
        }

        return $fin - $inicio;
    }

    private static function minutosDelDia(mixed $hora): ?int
    {
        if (! is_string($hora) || ! preg_match('/^(\d{1,2}):(\d{2})/', trim($hora), $coincide)) {
            return null;
        }

        $horas = (int) $coincide[1];
        $minutos = (int) $coincide[2];
        if ($horas > 23 || $minutos > 59) {
            return null;
        }

        return ($horas * 60) + $minutos;
    }
}
