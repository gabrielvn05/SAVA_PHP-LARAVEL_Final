<?php

namespace App\Http\Controllers;

use App\Services\FirmaEcService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class FirmaEcController extends Controller
{
    public function __construct(private readonly FirmaEcService $firmaEc) {}

    public function grabar(Request $request): Response
    {
        $esperada = (string) (config('firmaec.callback_key') ?: config('firmaec.api_key'));
        $recibida = (string) $request->header('X-API-KEY', '');
        if ($esperada === '' || ! hash_equals($esperada, $recibida)) {
            abort(403);
        }

        try {
            $this->firmaEc->recibirFirmado($request->all());
        } catch (ValidationException $e) {
            return response('0', 422, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return response('OK', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
