<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioDatosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViajeSolicitudTest extends TestCase
{
    use RefreshDatabase;

    public function test_viaje_guarda_fechas_evento_por_dia_mes_anio(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'cedula' => '1712345675',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $inicioFalta = now()->addDays(5)->toDateString();
        $finFalta = now()->addDays(10)->toDateString();

        $this->actingAs($user)
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Viaje->value,
                'fecha_inicio_viaje' => $inicioFalta,
                'fecha_fin_viaje' => $finFalta,
                'evento_inicio_dia' => 15,
                'evento_inicio_mes' => 9,
                'evento_inicio_anio' => 2026,
                'evento_fin_dia' => 20,
                'evento_fin_mes' => 9,
                'evento_fin_anio' => 2026,
                'tipo_viaje_evento' => 'congreso_expositor',
                'nombre_evento' => 'Congreso SAVA',
                'lugar_evento' => 'Guayaquil, Ecuador',
                'observaciones' => 'Presentación de ponencia.',
            ])
            ->assertRedirect();

        $solicitud = Solicitud::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame('Visita de campo: Congreso SAVA', $solicitud->motivo);
        $this->assertSame('2026-09-15', $solicitud->detalle['fecha_evento_desde'] ?? null);
        $this->assertSame('2026-09-20', $solicitud->detalle['fecha_evento_hasta'] ?? null);
        $this->assertSame(15, $solicitud->detalle['evento_inicio_dia'] ?? null);
        $this->assertSame(9, $solicitud->detalle['evento_inicio_mes'] ?? null);
    }

    public function test_viaje_rechaza_fecha_evento_invalida(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'cedula' => '1312000101',
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);

        $this->actingAs($user)
            ->from(route('solicitudes.wizard'))
            ->post(route('solicitudes.store'), [
                'tipo' => SolicitudTipo::Viaje->value,
                'fecha_inicio_viaje' => now()->toDateString(),
                'fecha_fin_viaje' => now()->addDay()->toDateString(),
                'evento_inicio_dia' => 31,
                'evento_inicio_mes' => 2,
                'evento_inicio_anio' => 2026,
                'evento_fin_dia' => 5,
                'evento_fin_mes' => 3,
                'evento_fin_anio' => 2026,
                'tipo_viaje_evento' => 'estudio',
                'nombre_evento' => 'Curso',
                'lugar_evento' => 'Quito',
            ])
            ->assertRedirect(route('solicitudes.wizard'))
            ->assertSessionHas('error');

        $this->assertSame(0, Solicitud::query()->count());
    }

    public function test_oficio_viaje_formatea_fechas_evento(): void
    {
        $user = User::factory()->create(['rol' => AppRole::Docente, 'carrera' => 'software']);

        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => '2026-09-10',
            'fecha_fin' => '2026-09-25',
            'motivo' => 'Permiso por viaje: Evento X',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'fecha_evento_desde' => '2026-09-15',
                'fecha_evento_hasta' => '2026-09-20',
                'nombre_evento' => 'Evento X',
                'tipo_viaje_evento' => 'estudio',
                'lugar_evento' => 'Manta',
            ],
        ]);

        $valores = app(OficioDatosService::class)->placeholders($solicitud);

        $this->assertSame(
            'desde el 15 hasta el 20 de septiembre de 2026',
            $valores['[Fechas del evento o actividad]'],
        );
    }
}
