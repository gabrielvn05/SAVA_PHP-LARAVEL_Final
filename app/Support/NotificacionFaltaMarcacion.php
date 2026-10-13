<?php

namespace App\Support;

class NotificacionFaltaMarcacion
{
    /** @return list<string> */
    public static function valores(): array
    {
        return ['entrada', 'salida', 'entrada_salida'];
    }

    public static function enOracion(?string $value): string
    {
        return match ($value) {
            'entrada' => 'entrada',
            'salida' => 'salida',
            'entrada_salida' => 'entrada y salida',
            default => $value ?? '—',
        };
    }

    public static function label(?string $value): string
    {
        return OficioEtiquetas::tipoMarcacion($value);
    }
}
