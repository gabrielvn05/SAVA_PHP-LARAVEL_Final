<?php

namespace App\Enums;

enum PermisoMotivo: string
{
    case Reunion = 'reunion';
    case PracticasVinculacion = 'practicas_vinculacion';
    case Familiares = 'familiares';
    case TramitesPersonales = 'tramites_personales';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Reunion => 'Reunión',
            self::PracticasVinculacion => 'Salida por prácticas o vinculación',
            self::Familiares => 'Asuntos familiares',
            self::TramitesPersonales => 'Trámites personales',
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
            self::Otro => 'Describa brevemente el motivo en el campo adicional.',
        };
    }
}
