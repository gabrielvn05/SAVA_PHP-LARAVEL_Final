<?php

namespace Tests\Unit;

use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Services\OficioDatosService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficioFechasViajeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fechas_evento_mismo_mes(): void
    {
        $user = User::factory()->create();
        $solicitud = Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => '2026-09-15',
            'fecha_fin' => '2026-09-20',
            'motivo' => 'Viaje de prueba',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => [
                'fecha_evento_desde' => '2026-09-15',
                'fecha_evento_hasta' => '2026-09-20',
            ],
        ]);

        Carbon::setLocale('es');
        $valores = app(OficioDatosService::class)->placeholders($solicitud);

        $this->assertSame(
            'desde el 15 hasta el 20 de septiembre de 2026',
            $valores['[Fechas del evento o actividad]'],
        );
    }
}
