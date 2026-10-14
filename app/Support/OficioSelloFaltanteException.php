<?php

namespace App\Support;

class OficioSelloFaltanteException extends \RuntimeException
{
    public static function make(): self
    {
        return new self(
            'No se encontró el sello de autorizado. Coloque sello-autorizado.jpg en resources/templates/oficio-media para emitir oficios aprobados.',
        );
    }
}
