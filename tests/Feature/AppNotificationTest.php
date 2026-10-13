<?php

namespace Tests\Feature;

use App\Enums\AppNotificationKind;
use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\AppNotification;
use App\Models\AccountRequest;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_nueva_solicitud_notifica_a_secretaria(): void
    {
        $docente = $this->usuario(AppRole::Docente, '1313000101');
        $secretaria = $this->usuario(AppRole::Secretaria, '1313000102');
        $solicitud = $this->crearSolicitud($docente, SolicitudEstado::EnRevisionSecretaria);

        $service = app(\App\Services\AppNotificationService::class);
        $service->onSolicitudCreada($solicitud);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $secretaria->id,
            'kind' => AppNotificationKind::SolicitudNueva->value,
        ]);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $docente->id,
        ]);
    }

    public function test_secretaria_aprueba_notifica_decano_y_solicitante(): void
    {
        $docente = $this->usuario(AppRole::Docente, '1313000103');
        $decano = $this->usuario(AppRole::Decano, '1313000104');
        $solicitud = $this->crearSolicitud($docente, SolicitudEstado::PendienteAprobacionDecano);

        $service = app(\App\Services\AppNotificationService::class);
        $service->onSolicitudRevisadaSecretaria(
            $solicitud,
            SolicitudEstado::PendienteAprobacionDecano,
            true,
        );

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $decano->id,
            'kind' => AppNotificationKind::SolicitudPendienteDecano->value,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $docente->id,
            'kind' => AppNotificationKind::SolicitudEstadoActualizado->value,
        ]);
    }

    public function test_solicitud_cuenta_notifica_decanos(): void
    {
        $decano = $this->usuario(AppRole::Decano, '1313000105');
        $accountRequest = AccountRequest::query()->create([
            'nombres' => 'Ana',
            'apellidos' => 'Pérez',
            'email' => 'ana@uleam.edu.ec',
            'cedula' => '1313000199',
            'carrera' => 'software',
            'status' => \App\Enums\AccountRequestStatus::Pendiente,
        ]);

        app(\App\Services\AppNotificationService::class)->onSolicitudCuentaCreada($accountRequest);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $decano->id,
            'kind' => AppNotificationKind::SolicitudCuentaNueva->value,
        ]);
    }

    public function test_abrir_notificacion_la_marca_leida_y_redirige(): void
    {
        $user = $this->usuario(AppRole::Docente, '1313000106');
        $notif = AppNotification::query()->create([
            'user_id' => $user->id,
            'kind' => AppNotificationKind::SolicitudResuelta->value,
            'title' => 'Prueba',
            'message' => 'Mensaje de prueba',
            'action_url' => route('dashboard'),
        ]);

        $this->actingAs($user)
            ->get(route('app-notificaciones.abrir', $notif))
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($notif->fresh()->read_at);
    }

    private function usuario(AppRole $rol, string $cedula): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'cedula' => $cedula,
            'carrera' => 'software',
            'celular' => '0991234567',
        ]);
    }

    private function crearSolicitud(User $creador, SolicitudEstado $estado): Solicitud
    {
        return Solicitud::query()->create([
            'creado_por' => $creador->id,
            'tipo' => SolicitudTipo::Viaje,
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDay()->toDateString(),
            'motivo' => 'Motivo de prueba',
            'estado' => $estado,
            'detalle' => ['codigo_tramite' => 'SAVA-NOTIF-001'],
        ]);
    }
}
