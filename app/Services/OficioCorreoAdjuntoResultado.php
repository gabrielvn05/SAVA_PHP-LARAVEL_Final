<?php

namespace App\Services;

use Illuminate\Mail\Mailables\Attachment;

class OficioCorreoAdjuntoResultado
{
    /** @param  list<Attachment>  $attachments */
    public function __construct(
        public readonly array $attachments,
        public readonly ?string $formato,
    ) {}
}
