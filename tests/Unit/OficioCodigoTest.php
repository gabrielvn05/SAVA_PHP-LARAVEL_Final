<?php

namespace Tests\Unit;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
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

    public function test_genera_sufijo_si_el_codigo_ya_existe(): void
    {
        $user = User::factory()->create([
            'nombres' => 'María José',
            'apellidos' => 'Pérez López',
            'rol' => AppRole::Administrativo,
        ]);

        $fecha = Carbon::create(2026, 3, 15, 10, 0, 0);
        $base = 'FACIVITEC-15032026-VIA-MJPL';

        Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => $fecha->toDateString(),
            'fecha_fin' => $fecha->copy()->addDay()->toDateString(),
            'motivo' => 'Primer viaje',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => ['codigo_tramite' => $base],
        ]);

        $this->assertSame($base.'-02', OficioCodigo::generar($user, SolicitudTipo::Viaje, $fecha));
    }
}
