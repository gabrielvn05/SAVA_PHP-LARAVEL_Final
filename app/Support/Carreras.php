<?php

namespace App\Support;

class Carreras
{
    /** @var list<array{value: string, label: string}> */
    public const OPCIONES = [
        ['value' => 'software', 'label' => 'SOFTWARE'],
        ['value' => 'tecnologias_informacion', 'label' => 'TECNOLOGIAS DE LA INFORMACION'],
        ['value' => 'agroindustria', 'label' => 'AGROINDUSTRIA'],
        ['value' => 'agropecuaria', 'label' => 'AGROPECUARIA'],
        ['value' => 'agronegocios', 'label' => 'AGRONEGOCIOS'],
        ['value' => 'biologia', 'label' => 'BIOLOGIA'],
        ['value' => 'ambiente', 'label' => 'AMBIENTE'],
    ];

    public static function label(string $value): string
    {
        foreach (self::OPCIONES as $carrera) {
            if ($carrera['value'] === $value) {
                return $carrera['label'];
            }
        }

        return $value ?: '—';
    }

    public static function isValid(string $value): bool
    {
        foreach (self::OPCIONES as $carrera) {
            if ($carrera['value'] === $value) {
                return true;
            }
        }

        return false;
    }
}
