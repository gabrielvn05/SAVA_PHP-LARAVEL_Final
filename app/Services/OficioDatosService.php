<?php

namespace App\Services;

use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\Carreras;
use App\Support\OficioCodigo;
use App\Support\OficioEtiquetas;
use Carbon\Carbon;

class OficioDatosService
{
    public const FACULTAD_NOMBRE = 'Facultad de Ciencias de la Vida y la Tecnología (FACIVITEC)';

    public const FACULTAD_ETIQUETA = 'FACIVITEC';

    /**
     * @return array<string, string>
     */
    public function placeholders(Solicitud $solicitud, ?User $decano = null): array
    {
        $solicitud->loadMissing('creador');
        $creador = $solicitud->creador;
        $decano ??= User::query()->where('rol', 'decano')->where('activo', true)->first();

        $detalle = $solicitud->detalle ?? [];
        $codigo = $detalle['codigo_tramite'] ?? OficioCodigo::generar($creador, $solicitud->tipo);

        $fechaInicio = $solicitud->fecha_inicio->format('d/m/Y');
        $fechaFin = $solicitud->fecha_fin->format('d/m/Y');
        $numeroDias = (string) ($solicitud->fecha_inicio->diffInDays($solicitud->fecha_fin) + 1);
        $nombreCompleto = $creador->nombreCompleto();
        $cedula = (string) ($detalle['cedula'] ?? $creador->cedula ?? '—');
        $carrera = Carreras::label($detalle['carrera'] ?? $creador->carrera ?? '');
        $rolPersonal = mb_strtoupper((string) ($detalle['tipo_personal'] ?? $creador->rol->label()), 'UTF-8');
        $fechaGeneracion = now()->locale('es')->translatedFormat('d \d\e F \d\e Y H:i');

        $base = [
            '[Código del oficio]' => $codigo,
            '[Nombre del decano]' => $decano?->nombreCompleto() ?? 'Decano(a) de Facultad',
            '[Nombre completo del docente]' => $nombreCompleto,
            '[Nombre completo del personal]' => $nombreCompleto,
            '[Nombre completo del PERSONAL]' => $nombreCompleto,
            '[Número de cédula]' => $cedula,
            '[Carrera]' => $carrera,
            '[facultad]' => self::FACULTAD_NOMBRE,
            '[Facultad]' => self::FACULTAD_ETIQUETA,
            '[Cédula]' => $cedula,
            '[Rol del personal]' => $rolPersonal,
            '[Fecha automática del sistema]' => now()->translatedFormat('d \d\e F \d\e Y'),
            '[Fecha y hora completa automática del sistema]' => $fechaGeneracion,
            '[Fecha de inicio de la falta]' => $fechaInicio,
            '[Fecha de la falta]' => $fechaInicio,
            '[Fecha de retorno a clases]' => $fechaFin,
            '[Fecha de retorno a actividades normales]' => $fechaFin,
            '[Fecha de retorno a actividades]' => $fechaFin,
            '[Número de días]' => $numeroDias,
        ];

        $tipo = array_merge($base, match ($solicitud->tipo) {
            SolicitudTipo::FaltaMarcado => [
                '[Jornada]' => OficioEtiquetas::jornada($detalle['jornada'] ?? null),
                '[Fecha del incidente]' => $this->formatFecha($detalle['fecha_incidente'] ?? $solicitud->fecha_inicio),
                '[Tipo de marcación omitida/fallida]' => OficioEtiquetas::tipoMarcacion($detalle['tipo_marcacion_omitida'] ?? null),
                '[Hora real de ingreso]' => \App\Support\SolicitudFaltaMarcadoRules::formatHora($detalle['hora_real_ingreso'] ?? null),
                '[Hora real de salida]' => \App\Support\SolicitudFaltaMarcadoRules::formatHora($detalle['hora_real_salida'] ?? null),
                '[Motivo]' => $solicitud->motivo ?: OficioEtiquetas::motivoFaltaRegistro($detalle['motivo_falta_registro'] ?? null),
                '[Motivo de la falta de registro]' => $solicitud->motivo ?: OficioEtiquetas::motivoFaltaRegistro($detalle['motivo_falta_registro'] ?? null),
                '[Observaciones adicionales]' => (string) ($detalle['observaciones'] ?? '—'),
            ],
            SolicitudTipo::CalamidadDomestica => [
                '[Tipo de calamidad]' => OficioEtiquetas::tipoCalamidad($detalle['tipo_calamidad'] ?? null),
                '[Nombre completo del familiar afectado]' => (string) ($detalle['nombre_familiar'] ?? '—'),
                '[Parentesco]' => OficioEtiquetas::parentesco($detalle['parentesco'] ?? null),
                '[Descripción del hecho]' => (string) ($detalle['descripcion_hecho'] ?? '—'),
                '[Lugar donde ocurrió]' => (string) ($detalle['lugar_suceso'] ?? '—'),
                '[Fecha del hecho]' => $this->formatFecha($detalle['fecha_hecho'] ?? null),
            ],
            SolicitudTipo::Enfermedad => [
                '[Lugar donde se realizó la atención médica]' => $this->institucionMedica($detalle),
                '[Nombre completo del doctor(a)]' => (string) ($detalle['medico_tratante'] ?? '—'),
                '[Diagnóstico principal]' => (string) ($detalle['diagnostico'] ?? '—'),
            ],
            SolicitudTipo::Viaje => [
                '[Tipo de viaje]' => OficioEtiquetas::tipoViaje($detalle['tipo_viaje_evento'] ?? null),
                '[Tipo de visita de campo]' => OficioEtiquetas::tipoViaje($detalle['tipo_viaje_evento'] ?? null),
                '[Tipo]' => OficioEtiquetas::tipoViaje($detalle['tipo_viaje_evento'] ?? null),
                '[Nombre del evento o institución de estudio]' => (string) ($detalle['nombre_evento'] ?? '—'),
                '[Nombre del lugar o institución de estudio]' => (string) ($detalle['nombre_evento'] ?? '—'),
                '[Lugar (ciudad, país)]' => (string) ($detalle['lugar_evento'] ?? '—'),
                '[Fechas del evento o actividad]' => $this->formatFechasEventoViaje(
                    $detalle['fecha_evento_desde'] ?? $solicitud->fecha_inicio,
                    $detalle['fecha_evento_hasta'] ?? $solicitud->fecha_fin,
                ),
                '[Rol específico]' => (string) ($detalle['rol_especifico'] ?? '—'),
                '[Objetivo académico]' => (string) ($solicitud->motivo ?? '—'),
                '[Observaciones adicionales]' => (string) ($detalle['observaciones'] ?? '—'),
                '[Observaciones Adicionales]' => (string) ($detalle['observaciones'] ?? '—'),
            ],
            SolicitudTipo::Permiso => [
                '[Fecha del permiso]' => $this->formatFecha($detalle['fecha_permiso'] ?? $solicitud->fecha_inicio),
                '[Hora de inicio de falta]' => \App\Support\SolicitudPermisoRules::formatHora((string) ($detalle['hora_inicio_permiso'] ?? '')),
                '[Hora de fin de falta]' => \App\Support\SolicitudPermisoRules::formatHora((string) ($detalle['hora_fin_permiso'] ?? '')),
                '[Motivo]' => (string) ($solicitud->motivo ?? '—'),
                '[Observaciones adicionales]' => (string) ($detalle['observaciones'] ?? '—'),
            ],
            SolicitudTipo::Justificacion => [
                '[Fecha de atraso]' => $this->formatFecha($detalle['fecha_atraso'] ?? $solicitud->fecha_inicio),
                '[Hora establecida de llegada]' => \App\Support\SolicitudJustificacionAtrasoRules::formatHora(
                    (string) ($detalle['hora_llegada_establecida'] ?? ''),
                ),
                '[Hora de llegada de atraso]' => \App\Support\SolicitudJustificacionAtrasoRules::formatHora(
                    (string) ($detalle['hora_llegada_real'] ?? ''),
                ),
                '[Motivo]' => (string) ($solicitud->motivo ?? '—'),
                '[Observaciones adicionales]' => (string) ($detalle['observaciones'] ?? '—'),
            ],
            default => [],
        });

        $tipo['_correo_docente'] = $creador->email;
        $tipo['_correo_decano'] = $decano?->email ?? '—';

        return $tipo;
    }

    public function plantillaPath(SolicitudTipo $tipo): ?string
    {
        $file = match ($tipo) {
            SolicitudTipo::FaltaMarcado => 'reconocimiento.docx',
            SolicitudTipo::CalamidadDomestica => 'calamidad.docx',
            SolicitudTipo::Enfermedad => 'medico.docx',
            SolicitudTipo::Viaje => 'viaje.docx',
            SolicitudTipo::Permiso => 'permiso.docx',
            SolicitudTipo::Justificacion => 'justificacion-atraso.docx',
            default => null,
        };

        if ($file === null) {
            return null;
        }

        $path = resource_path('templates/oficios/'.$file);

        return is_readable($path) ? $path : null;
    }

    /**
     * @param  array<string, mixed>  $detalle
     */
    private function institucionMedica(array $detalle): string
    {
        $tipo = $detalle['institucion_medica_tipo'] ?? null;
        $nombre = $detalle['institucion_medica'] ?? $detalle['institucion_medica_nombre'] ?? null;

        if ($tipo === 'IESS') {
            return 'IESS';
        }

        if ($nombre && $tipo && $tipo !== 'Otro') {
            return (string) $nombre;
        }

        if ($nombre && $tipo === 'Otro') {
            return (string) $nombre;
        }

        return (string) ($nombre ?? $tipo ?? '—');
    }

    private function formatFecha(mixed $value): string
    {
        $fecha = $this->parseFecha($value);

        return $fecha?->format('d/m/Y') ?? '—';
    }

    private function formatFechasEventoViaje(mixed $desde, mixed $hasta): string
    {
        $inicio = $this->parseFecha($desde);
        $fin = $this->parseFecha($hasta);

        if ($inicio === null || $fin === null) {
            return '—';
        }

        Carbon::setLocale('es');

        if ($inicio->month === $fin->month && $inicio->year === $fin->year) {
            return sprintf(
                'desde el %s hasta el %s de %s de %s',
                $inicio->format('j'),
                $fin->format('j'),
                $fin->translatedFormat('F'),
                $fin->format('Y'),
            );
        }

        if ($inicio->year === $fin->year) {
            return sprintf(
                'desde el %s de %s hasta el %s de %s de %s',
                $inicio->format('j'),
                $inicio->translatedFormat('F'),
                $fin->format('j'),
                $fin->translatedFormat('F'),
                $fin->format('Y'),
            );
        }

        return sprintf(
            'desde el %s de %s de %s hasta el %s de %s de %s',
            $inicio->format('j'),
            $inicio->translatedFormat('F'),
            $inicio->format('Y'),
            $fin->format('j'),
            $fin->translatedFormat('F'),
            $fin->format('Y'),
        );
    }

    private function parseFecha(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
