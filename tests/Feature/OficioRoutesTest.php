<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class OficioRoutesTest extends TestCase
{
    public function test_rutas_de_oficio_estan_registradas(): void
    {
        $this->assertTrue(Route::has('solicitudes.preview-oficio'));
        $this->assertTrue(Route::has('solicitudes.oficio-documento'));
        $this->assertTrue(Route::has('solicitudes.oficio-descargar'));
        $this->assertTrue(Route::has('solicitudes.adjunto'));
    }
}
