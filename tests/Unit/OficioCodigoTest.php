<?php

namespace Tests\Unit;

use App\Enums\SolicitudTipo;
use App\Models\User;
use App\Support\OficioCodigo;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficioCodigoTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_codigo_con_estructura_facivitec(): void
    {
        $user = User::factory()->create([
            'nombres' => 'María José',
            'apellidos' => 'Pérez López',
        ]);

        $fecha = Carbon::create(2026, 3, 15, 10, 0, 0);
        $codigo = OficioCodigo::generar($user, SolicitudTipo::Viaje, $fecha);

        $this->assertSame('FACIVITEC-15032026-VIA-MJPL', $codigo);
    }
}
