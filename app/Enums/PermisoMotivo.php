<?php

namespace App\Enums;

enum PermisoMotivo: string
{
    case Reunion = 'reunion';
    case PracticasVinculacion = 'practicas_vinculacion';
    case Familiares = 'familiares';
    case TramitesPersonales = 'tramites_personales';
    case CitaMedica = 'cita_medica';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Reunion => 'Reunión',
            self::PracticasVinculacion => 'Salida por prácticas o vinculación',
            self::Familiares => 'Asuntos familiares',
            self::TramitesPersonales => 'Trámites personales',
            self::CitaMedica => 'Cita médica',
            self::Otro => 'Otra opción',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Reunion => 'Actividades académicas o administrativas en la institución.',
            self::PracticasVinculacion => 'Salida relacionada con prácticas preprofesionales o vinculación con la comunidad.',
            self::Familiares => 'Olimpiadas, reuniones escolares de hijos, citas familiares urgentes, etc.',
            self::TramitesPersonales => 'Sacar o renovar cédula/licencia, trámites bancarios u otros personales.',
            self::CitaMedica => 'Ausencia para asistir a una cita médica en el horario indicado.',
            self::Otro => 'Describa brevemente el motivo en el campo adicional.',
        };
    }

    public function avisoAdjunto(): ?string
    {
        return match ($this) {
            self::CitaMedica => 'Es obligatorio subir la orden o citación médica que verifique la cita en ese horario.',
            default => null,
        };
    }
}
