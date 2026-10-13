<?php

namespace App\Support;

use App\Models\NotificacionFalta;

class NotificacionFaltaCodigo
{
    public const PREFIJO = 'FACIVITEC-ADM';

    public static function siguiente(): string
    {
        $ultimo = NotificacionFalta::query()
            ->where('codigo_oficio', 'like', self::PREFIJO.'-%')
            ->orderByDesc('codigo_oficio')
            ->value('codigo_oficio');

        $numero = 1;
        if (is_string($ultimo) && preg_match('/-(\d+)$/', $ultimo, $matches)) {
            $numero = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%04d', self::PREFIJO, $numero);
    }
}
