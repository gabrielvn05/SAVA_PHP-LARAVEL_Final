<?php

namespace App\Support;

class OficioEtiquetas
{
    public static function tipoCalamidad(?string $value): string
    {
        return match ($value) {
            'fallecimiento_familiar' => 'Fallecimiento de familiar',
            'emergencia_medica_familiar' => 'Emergencia médica grave de familiar',
            default => $value ?? '—',
        };
    }

    public static function parentesco(?string $value): string
    {
        return match ($value) {
            'conyuge' => 'Cónyuge',
            'madre' => 'Madre',
            'padre' => 'Padre',
            'hermano' => 'Hermano(a)',
            'hijo' => 'Hijo(a)',
            default => $value ?? '—',
        };
    }

    public static function tipoViaje(?string $value): string
    {
        return match ($value) {
            'estudio' => 'Estudio',
            'congreso_expositor' => 'Congreso – Expositor',
            'congreso_participante' => 'Congreso – Participante (observador)',
            default => $value ?? '—',
        };
    }

    public static function tipoMarcacion(?string $value): string
    {
        return match ($value) {
            'entrada' => 'Marcación de entrada',
            'salida' => 'Marcación de salida',
            'entrada_salida' => 'Entrada y salida',
            default => $value ?? '—',
        };
    }

    public static function motivoFaltaRegistro(?string $value): string
    {
        return match ($value) {
            'olvido_docente' => 'Olvido del docente',
            'falla_face_id' => 'Falla técnica del sistema Face ID',
            default => $value ?? '—',
        };
    }

    public static function jornada(?string $value): string
    {
        return match ($value) {
            'primera_jornada' => 'Primera jornada',
            'segunda_jornada' => 'Segunda jornada',
            'ambas' => 'Ambas jornadas',
            default => $value ?? '—',
        };
    }
}
