<?php

namespace App\Support;

readonly class CorreoEnvioResultado
{
    public function __construct(
        public bool $enviadoAlBuzon,
        public string $mensajeUsuario,
        public ?string $errorTecnico = null,
    ) {}

    public function flashTipo(): string
    {
        return $this->enviadoAlBuzon ? 'success' : 'error';
    }
}
