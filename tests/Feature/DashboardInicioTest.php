<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardInicioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_saludo_dice_los_tramites_en_proceso_antes_de_los_graficos(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'nombres' => 'Gabriel',
        ]);
        Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Permiso,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-10',
            'motivo' => 'Trámite personal',
            'estado' => SolicitudEstado::EnBorrador,
            'detalle' => [],
        ]);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hola, Gabriel')
            ->assertSee('1 trámite en proceso')
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Distribución por estado'),
            strpos($html, 'Nueva solicitud'),
        );
    }

    public function test_secretaria_ve_la_cola_de_revision_junto_al_saludo(): void
    {
        $solicitante = User::factory()->create(['rol' => AppRole::Docente]);
        $secretaria = User::factory()->create([
            'rol' => AppRole::Secretaria,
            'nombres' => 'Ana',
        ]);
        Solicitud::query()->create([
            'creado_por' => $solicitante->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => '2026-11-10',
            'fecha_fin' => '2026-11-12',
            'motivo' => 'Visita de campo',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [],
        ]);

        $html = $this->actingAs($secretaria)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hola, Ana')
            ->assertSee('1 pendiente de tu revisión')
            ->assertSee('Abrir proceso')
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Distribución por estado'),
            strpos($html, 'Abrir proceso'),
        );
    }
}
