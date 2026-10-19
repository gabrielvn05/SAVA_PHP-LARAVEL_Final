<?php

namespace Tests\Unit;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\MatrizNovedades;
use Tests\TestCase;

class MatrizNovedadesTest extends TestCase
{
    public function test_una_jornada_equivale_a_ocho_horas(): void
    {
        $this->assertSame(
            ['dias' => 1, 'horas' => 1, 'minutos' => 0],
            MatrizNovedades::descomponer(540),
        );
        $this->assertSame(
            ['dias' => 0, 'horas' => 2, 'minutos' => 30],
            MatrizNovedades::descomponer(150),
        );
    }

    public function test_permiso_de_nueve_horas_pasa_un_dia_de_jornada(): void
    {
        $docente = new User([
            'rol' => AppRole::Docente,
            'nombres' => 'Miguel',
            'apellidos' => 'Zamora',
            'cedula' => '1314977255',
        ]);
        $solicitud = new Solicitud([
            'tipo' => SolicitudTipo::Permiso,
            'estado' => SolicitudEstado::Aprobada,
            'fecha_inicio' => '2026-10-07',
            'fecha_fin' => '2026-10-07',
            'motivo' => 'Reunión',
            'detalle' => [
                'motivo_permiso' => 'reunion',
                'hora_inicio_permiso' => '08:00',
                'hora_fin_permiso' => '17:00',
                'codigo_tramite' => 'FACIVITEC-PER-09',
            ],
        ]);
        $solicitud->setRelation('creador', $docente);

        $fila = MatrizNovedades::fila($solicitud);

        $this->assertSame(1, $fila['dias']);
        $this->assertSame(1, $fila['horas']);
        $this->assertSame(0, $fila['minutos']);
        $this->assertSame('J', $fila['novedad']);
    }
}
