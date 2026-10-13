<?php

namespace App\Enums;

enum JustificacionAtrasoMotivo: string
{
    case AveriaTransporte = 'averia_transporte';
    case ProblemasViales = 'problemas_viales';
    case CuidadoDependientes = 'cuidado_dependientes';

    public function label(): string
    {
        return match ($this) {
            self::AveriaTransporte => 'Avería de transporte',
            self::ProblemasViales => 'Problemas viales',
            self::CuidadoDependientes => 'Cuidado de dependientes',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::AveriaTransporte => 'Incluye daño a vehículo propio o retraso del transporte público.',
            self::ProblemasViales => 'Accidentes, eventos de naturaleza (deslaves, etc.) o cierre de vías.',
            self::CuidadoDependientes => 'Familiar hasta primer grado por demora de la persona que lo cuida.',
        };
    }
}
