<?php

namespace Tests\Unit;

use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Support\SolicitudPeriodo;
use Tests\TestCase;

class SolicitudPeriodoTest extends TestCase
{
    public function test_mismo_mes_se_lee_como_rango_corto(): void
    {
        $solicitud = $this->solicitud('2026-03-01', '2026-03-03');

        $this->assertSame('1–3 mar 2026', SolicitudPeriodo::label($solicitud));
    }

    public function test_meses_distintos_del_mismo_ano_conservan_el_anio_una_vez(): void
    {
        $solicitud = $this->solicitud('2026-02-28', '2026-03-02');

        $this->assertSame('28 feb – 2 mar 2026', SolicitudPeriodo::label($solicitud));
    }

    public function test_permiso_de_un_dia_incluye_la_hora(): void
    {
        $solicitud = $this->solicitud('2026-03-01', '2026-03-01', SolicitudTipo::Permiso, [
            'hora_inicio_permiso' => '08:00',
            'hora_fin_permiso' => '10:30',
        ]);

        $this->assertSame('1 mar 2026 · 08:00–10:30', SolicitudPeriodo::label($solicitud));
    }

    /**
     * @param  array<string, string>  $detalle
     */
    private function solicitud(string $inicio, string $fin, SolicitudTipo $tipo = SolicitudTipo::Viaje, array $detalle = []): Solicitud
    {
        return new Solicitud([
            'tipo' => $tipo,
            'estado' => SolicitudEstado::EnBorrador,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'motivo' => 'Prueba',
            'detalle' => $detalle,
        ]);
    }
}
