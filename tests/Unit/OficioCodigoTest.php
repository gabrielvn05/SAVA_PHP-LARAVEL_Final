<?php

namespace Tests\Unit;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\OficioCodigo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficioCodigoTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_codigo_con_rol_tipo_y_correlativo(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Administrativo,
            'nombres' => 'María José',
            'apellidos' => 'Pérez López',
        ]);

        $codigo = OficioCodigo::generar($user, SolicitudTipo::CalamidadDomestica);

        $this->assertSame('FACIVITEC-ADM-CDOM-0001', $codigo);
    }

    public function test_siglas_de_rol_y_tipo(): void
    {
        $this->assertSame('DOC', OficioCodigo::siglasRol(AppRole::Docente));
        $this->assertSame('ADM', OficioCodigo::siglasRol(AppRole::Administrativo));
        $this->assertSame('CDT', OficioCodigo::siglasRol(AppRole::Mantenimiento));

        $this->assertSame('PERM', OficioCodigo::siglasTipo(SolicitudTipo::Permiso));
        $this->assertSame('JUST', OficioCodigo::siglasTipo(SolicitudTipo::Justificacion));
        $this->assertSame('CERTMED', OficioCodigo::siglasTipo(SolicitudTipo::Enfermedad));
        $this->assertSame('VISCAM', OficioCodigo::siglasTipo(SolicitudTipo::Viaje));
        $this->assertSame('CDOM', OficioCodigo::siglasTipo(SolicitudTipo::CalamidadDomestica));
        $this->assertSame('NMAR', OficioCodigo::siglasTipo(SolicitudTipo::FaltaMarcado));
    }

    public function test_el_correlativo_sigue_dentro_de_la_misma_serie(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Docente,
            'nombres' => 'María José',
            'apellidos' => 'Pérez López',
        ]);

        $this->crearSolicitud($user, 'FACIVITEC-DOC-VISCAM-0004');
        $this->crearSolicitud($user, 'FACIVITEC-15032026-VIA-MJPL');

        $this->assertSame('FACIVITEC-DOC-VISCAM-0005', OficioCodigo::generar($user, SolicitudTipo::Viaje));
        $this->assertSame('FACIVITEC-DOC-PERM-0001', OficioCodigo::generar($user, SolicitudTipo::Permiso));
    }

    public function test_codigo_de_trabajo_usa_su_propia_serie(): void
    {
        $user = User::factory()->create([
            'rol' => AppRole::Mantenimiento,
        ]);

        $this->crearSolicitud($user, 'FACIVITEC-ADM-NMAR-0009');

        $this->assertSame('FACIVITEC-CDT-NMAR-0001', OficioCodigo::generar($user, SolicitudTipo::FaltaMarcado));
    }

    private function crearSolicitud(User $user, string $codigo): void
    {
        Solicitud::query()->create([
            'creado_por' => $user->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->toDateString(),
            'motivo' => 'Trámite de prueba',
            'estado' => SolicitudEstado::EnRevisionSecretaria,
            'detalle' => ['codigo_tramite' => $codigo],
        ]);
    }
}
