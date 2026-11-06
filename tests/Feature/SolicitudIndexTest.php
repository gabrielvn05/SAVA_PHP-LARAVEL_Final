<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitudIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_mis_solicitudes_muestra_periodo_legible_sin_columnas_repetidas(): void
    {
        $user = User::factory()->create(['rol' => AppRole::Docente]);
        Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-03-03',
            'motivo' => 'Congreso de redes',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => [],
        ]);

        $this->actingAs($user)
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('1–3 mar 2026')
            ->assertSee('Congreso de redes')
            ->assertSee('Visitas de campo')
            ->assertSee('>Tipo<', false)
            ->assertDontSee('>Carrera<', false)
            ->assertDontSee('>Rol<', false)
            ->assertDontSee('>Justificativo<', false);
    }

    public function test_listado_vacio_con_filtro_ofrece_quitarlo(): void
    {
        $user = User::factory()->create(['rol' => AppRole::Docente]);
        Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-03-03',
            'motivo' => 'Congreso de redes',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => [],
        ]);

        $this->actingAs($user)
            ->get(route('solicitudes.index', ['estado' => SolicitudEstado::Rechazada->value]))
            ->assertOk()
            ->assertSee('Ninguna solicitud coincide')
            ->assertSee('Quitar filtros')
            ->assertSee('Limpiar');
    }

    public function test_listado_vacio_sin_filtros_invita_a_crear(): void
    {
        $user = User::factory()->create(['rol' => AppRole::Docente]);

        $this->actingAs($user)
            ->get(route('solicitudes.index'))
            ->assertOk()
            ->assertSee('Aún no tienes solicitudes')
            ->assertSee('Nueva solicitud');
    }
}
