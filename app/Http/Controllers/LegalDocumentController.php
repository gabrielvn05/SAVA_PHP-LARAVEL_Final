<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LegalDocumentController extends Controller
{
    public function show(string $documento): BinaryFileResponse
    {
        $archivo = match ($documento) {
            'terminos' => 'terminos-condiciones-sava-uleam.pdf',
            'privacidad' => 'aviso-privacidad-sava-uleam.pdf',
            default => abort(404),
        };

        $path = resource_path('legal/'.$archivo);
        abort_unless(is_readable($path), 404);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$archivo.'"',
        ]);
    }
}
