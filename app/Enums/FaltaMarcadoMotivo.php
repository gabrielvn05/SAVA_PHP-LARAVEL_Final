<?php

namespace App\Enums;

enum FaltaMarcadoMotivo: string
{
    case OlvidoDocente = 'olvido_docente';
    case FallaFaceId = 'falla_face_id';

    public function label(): string
    {
        return match ($this) {
            self::OlvidoDocente => 'Olvido del docente',
            self::FallaFaceId => 'Falla técnica del sistema Face ID',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::OlvidoDocente => 'No registró a tiempo la marcación correspondiente.',
            self::FallaFaceId => 'El sistema no reconoció el rostro o presentó error al registrar.',
        };
    }
}
