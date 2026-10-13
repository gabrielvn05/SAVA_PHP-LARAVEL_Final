<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UsuarioAdminRoutesTest extends TestCase
{
    public function test_rutas_admin_usuarios_estan_registradas(): void
    {
        $this->assertTrue(Route::has('admin.usuarios.index'));
        $this->assertTrue(Route::has('admin.usuarios.store'));
        $this->assertTrue(Route::has('admin.usuarios.rol'));
        $this->assertTrue(Route::has('admin.usuarios.estado'));
        $this->assertTrue(Route::has('admin.usuarios.reset-clave'));
        $this->assertTrue(Route::has('admin.usuarios.import-csv'));
        $this->assertTrue(Route::has('admin.usuarios.delegate'));
    }
}
