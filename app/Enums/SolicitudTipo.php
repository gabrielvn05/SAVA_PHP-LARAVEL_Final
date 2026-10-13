<?php

namespace App\Enums;

enum SolicitudTipo: string
{
    case Permiso = 'permiso';
    case Justificacion = 'justificacion';
    case Viaje = 'viaje';
    case Enfermedad = 'enfermedad';
    case CalamidadDomestica = 'calamidad_domestica';
    case FaltaMarcado = 'falta_marcado';

    public function label(): string
    {
        return match ($this) {
            self::Permiso => 'Permiso',
            self::Justificacion => 'Justificación por atraso',
            self::Viaje => 'Visitas de campo',
            self::Enfermedad => 'Cita médica / certificado de salud',
            self::CalamidadDomestica => 'Calamidad doméstica',
            self::FaltaMarcado => 'Reporte de novedad en marcación',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Permiso => 'Permiso por horas en un solo día (reunión, trámites, prácticas u otros motivos autorizados).',
            self::Justificacion => 'Justificación del atraso en la jornada del día actual (horario y motivo).',
            self::Viaje => 'Justificación de ausencia por visitas técnicas de campo, congresos, capacitaciones u otras actividades fuera de la ciudad o país.',
            self::Enfermedad => 'Para justificar ausencia por enfermedad o cita médica (según tu rol: docente, administrativo o mantenimiento).',
            self::CalamidadDomestica => 'Para emergencias o situaciones familiares graves que impidan asistir.',
            self::FaltaMarcado => 'Justificante por olvidos o fallas del sistema Face ID.',
        };
    }
}
