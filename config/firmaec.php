<?php

return [

    /*
    | API de FirmaEC (MINTEL). Pruebas: impws. Producción: ws.
    | El sistema y la clave los entrega Gobierno Electrónico al registrar a SAVA
    | como sistema requirente. Sin ellos no se puede abrir FirmaEC.
    */
    'base_url' => env('FIRMAEC_BASE_URL', 'https://impws.firmadigital.gob.ec'),

    'api_key' => env('FIRMAEC_API_KEY'),

    'sistema' => env('FIRMAEC_SISTEMA'),

    /*
    | Clave que FirmaEC envía en X-API-KEY al devolver el PDF firmado.
    | Si no se define, se usa FIRMAEC_API_KEY.
    */
    'callback_key' => env('FIRMAEC_CALLBACK_KEY'),

    'pruebas' => filter_var(env('FIRMAEC_PRUEBAS', true), FILTER_VALIDATE_BOOL),

    /*
    | information2 = sello ETSI que estampa la aplicación FirmaEC.
    | QR = sello con código QR.
    */
    'estampado' => env('FIRMAEC_ESTAMPADO', 'information2'),

    'razon' => env('FIRMAEC_RAZON', 'Firmado desde SAVA ULEAM'),

];
