<?php

namespace App\Services;

use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;

class OficioPreviewService
{
    public function __construct(
        private readonly OficioDatosService $datos,
        private readonly OficioDocxService $docx,
    ) {}

    public function puedeUsarPlantillaDocx(Solicitud $solicitud): bool
    {
        return $this->docx->puedeGenerarDocx($solicitud);
    }

    public function renderHtmlRespaldo(Solicitud $solicitud, ?User $decano = null): string
    {
        $solicitud->loadMissing('creador');
        $decano ??= User::query()->where('rol', 'decano')->where('activo', true)->first();

        $detalle = $solicitud->detalle ?? [];
        $valores = $this->datos->placeholders($solicitud, $decano);
        $codigo = $valores['[Código del oficio]'] ?? strtoupper(substr($solicitud->id, 0, 8));

        $vista = match ($solicitud->tipo) {
            SolicitudTipo::FaltaMarcado => 'certificado.oficios.reconocimiento',
            SolicitudTipo::CalamidadDomestica => 'certificado.oficios.calamidad',
            SolicitudTipo::Enfermedad => 'certificado.oficios.medico',
            SolicitudTipo::Viaje => 'certificado.oficios.viaje',
            SolicitudTipo::Permiso => 'certificado.oficios.permiso',
            SolicitudTipo::Justificacion => 'certificado.oficios.justificacion-atraso',
            default => 'certificado.preview',
        };

        $base = [
            'solicitud' => $solicitud,
            'decano' => $decano,
            'codigo' => $codigo,
            'facultadNombre' => OficioDatosService::FACULTAD_NOMBRE,
            'nombreCompleto' => $valores['[Nombre completo del docente]'],
            'cedula' => $valores['[Cédula]'],
            'carrera' => $valores['[Carrera]'],
            'correo' => $valores['_correo_docente'] ?? $solicitud->creador->email,
            'rolPersonal' => $detalle['tipo_personal'] ?? $solicitud->creador->rol->label(),
            'fechaGeneracion' => $valores['[Fecha y hora completa automática del sistema]']
                ?? $valores['[Fecha automática del sistema]'],
        ];

        if ($vista === 'certificado.preview') {
            return view($vista, array_merge($base, [
                'logoFacultad' => asset('templates/oficio-media/logo-facultad.png'),
                'marcaUleam' => asset('templates/oficio-media/marca-uleam.png'),
            ]))->render();
        }

        return view($vista, array_merge($base, $this->extrasHtml($solicitud, $valores)))->render();
    }

    /**
     * @param  array<string, string>  $valores
     * @return array<string, mixed>
     */
    private function extrasHtml(Solicitud $solicitud, array $valores): array
    {
        return match ($solicitud->tipo) {
            SolicitudTipo::FaltaMarcado => self::extrasFaltaMarcado($solicitud, $valores),
            SolicitudTipo::CalamidadDomestica => [
                'fechaInicio' => $valores['[Fecha de inicio de la falta]'],
                'fechaFin' => $valores['[Fecha de retorno a clases]'],
                'numeroDias' => $valores['[Número de días]'],
                'tipoCalamidad' => $valores['[Tipo de calamidad]'],
                'nombreFamiliar' => $valores['[Nombre completo del familiar afectado]'],
                'parentesco' => $valores['[Parentesco]'],
                'descripcionHecho' => $valores['[Descripción del hecho]'],
                'lugarSuceso' => $valores['[Lugar donde ocurrió]'],
                'fechaHecho' => $valores['[Fecha del hecho]'],
            ],
            SolicitudTipo::Enfermedad => [
                'fechaInicio' => $valores['[Fecha de inicio de la falta]'],
                'fechaFin' => $valores['[Fecha de retorno a clases]'],
                'numeroDias' => $valores['[Número de días]'],
                'institucionMedica' => $valores['[Lugar donde se realizó la atención médica]'],
                'medicoTratante' => $valores['[Nombre completo del doctor(a)]'],
                'diagnostico' => $valores['[Diagnóstico principal]'],
            ],
            SolicitudTipo::Viaje => self::extrasViaje($solicitud, $valores),
            SolicitudTipo::Permiso => self::extrasPermiso($solicitud),
            SolicitudTipo::Justificacion => self::extrasJustificacionAtraso($solicitud),
            default => [],
        };
    }

    /**
     * @param  array<string, string>  $valores
     * @return array<string, mixed>
     */
    private static function extrasViaje(Solicitud $solicitud, array $valores): array
    {
        $detalle = $solicitud->detalle ?? [];

        return [
            'fechaInicio' => $valores['[Fecha de inicio de la falta]'],
            'fechaFin' => $valores['[Fecha de retorno a clases]'],
            'numeroDias' => $valores['[Número de días]'],
            'tipoViaje' => $valores['[Tipo de visita de campo]'] ?? $valores['[Tipo de viaje]'],
            'nombreEvento' => $valores['[Nombre del lugar o institución de estudio]']
                ?? $valores['[Nombre del evento o institución de estudio]'],
            'lugarEvento' => $valores['[Lugar (ciudad, país)]'],
            'fechasEventoActividad' => $valores['[Fechas del evento o actividad]'],
            'rolEspecifico' => ($valores['[Rol específico]'] ?? '—') !== '—' ? $valores['[Rol específico]'] : null,
            'objetivoAcademico' => ($valores['[Objetivo académico]'] ?? '—') !== '—' ? $valores['[Objetivo académico]'] : null,
            'observacionesAdicionales' => $detalle['observaciones'] ?? null,
        ];
    }

    /**
     * @param  array<string, string>  $valores
     * @return array<string, mixed>
     */
    private static function extrasFaltaMarcado(Solicitud $solicitud, array $valores): array
    {
        $detalle = $solicitud->detalle ?? [];

        return [
            'jornada' => $valores['[Jornada]'] ?? '—',
            'fechaIncidente' => $valores['[Fecha del incidente]'],
            'tipoMarcacion' => $valores['[Tipo de marcación omitida/fallida]'],
            'horaIngreso' => $valores['[Hora real de ingreso]'],
            'horaSalida' => $valores['[Hora real de salida]'],
            'motivoFaltaRegistro' => $valores['[Motivo]'] ?? $valores['[Motivo de la falta de registro]'],
            'observacionesAdicionales' => $detalle['observaciones'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private static function extrasPermiso(Solicitud $solicitud): array
    {
        $detalle = $solicitud->detalle ?? [];
        $fecha = $detalle['fecha_permiso'] ?? $solicitud->fecha_inicio?->format('Y-m-d');
        $fechaFmt = $fecha
            ? \Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('d \d\e F \d\e Y')
            : '—';

        return [
            'fechaPermiso' => $fechaFmt,
            'horaInicioFalta' => \App\Support\SolicitudPermisoRules::formatHora((string) ($detalle['hora_inicio_permiso'] ?? '')),
            'horaFinFalta' => \App\Support\SolicitudPermisoRules::formatHora((string) ($detalle['hora_fin_permiso'] ?? '')),
            'motivoPermiso' => $solicitud->motivo,
            'observacionesAdicionales' => $detalle['observaciones'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private static function extrasJustificacionAtraso(Solicitud $solicitud): array
    {
        $detalle = $solicitud->detalle ?? [];
        $fecha = $detalle['fecha_atraso'] ?? $solicitud->fecha_inicio?->format('Y-m-d');
        $fechaFmt = $fecha
            ? \Carbon\Carbon::parse($fecha)->locale('es')->translatedFormat('d \d\e F \d\e Y')
            : '—';

        return [
            'fechaAtraso' => $fechaFmt,
            'horaLlegadaEstablecida' => \App\Support\SolicitudJustificacionAtrasoRules::formatHora(
                (string) ($detalle['hora_llegada_establecida'] ?? '')
            ),
            'horaLlegadaReal' => \App\Support\SolicitudJustificacionAtrasoRules::formatHora(
                (string) ($detalle['hora_llegada_real'] ?? '')
            ),
            'motivoAtraso' => $solicitud->motivo,
            'observacionesAdicionales' => $detalle['observaciones'] ?? null,
        ];
    }
}
