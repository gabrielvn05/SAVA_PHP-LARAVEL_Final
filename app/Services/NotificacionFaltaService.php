<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\CapabilityType;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\NotificacionFalta;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\Carreras;
use App\Support\DelegacionDecano;
use App\Support\NotificacionFaltaCodigo;
use App\Support\NotificacionFaltaMarcacion;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificacionFaltaService
{
    public function decanoActivo(): ?User
    {
        return User::query()
            ->where('rol', AppRole::Decano)
            ->where('activo', true)
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Solo una justificación por marcado ya enviada (no borrador ni rechazada)
     * de ese colaborador, y que cubra la fecha de la notificación.
     * Un permiso, viaje u otro trámite no cambia este estado.
     */
    public function tieneJustificacionPorMarcado(User $destinatario, CarbonInterface $fecha): bool
    {
        return Solicitud::query()
            ->where('creado_por', $destinatario->id)
            ->where('tipo', SolicitudTipo::FaltaMarcado)
            ->whereNotIn('estado', [
                SolicitudEstado::EnBorrador,
                SolicitudEstado::Rechazada,
            ])
            ->whereDate('fecha_inicio', '<=', $fecha->toDateString())
            ->whereDate('fecha_fin', '>=', $fecha->toDateString())
            ->exists();
    }

    public function sincronizarPorSolicitud(Solicitud $solicitud): void
    {
        if ($solicitud->tipo !== SolicitudTipo::FaltaMarcado) {
            return;
        }

        if ($solicitud->fecha_inicio === null || $solicitud->fecha_fin === null || $solicitud->creado_por === null) {
            return;
        }

        $this->sincronizarUsuarioEnRango(
            (string) $solicitud->creado_por,
            $solicitud->fecha_inicio,
            $solicitud->fecha_fin,
        );
    }

    public function sincronizarUsuarioEnRango(string $userId, CarbonInterface $inicio, CarbonInterface $fin): void
    {
        $destinatario = User::query()->find($userId);
        if ($destinatario === null) {
            return;
        }

        NotificacionFalta::query()
            ->where('destinatario_id', $userId)
            ->whereDate('fecha_falta', '>=', $inicio->toDateString())
            ->whereDate('fecha_falta', '<=', $fin->toDateString())
            ->get()
            ->each(function (NotificacionFalta $notificacion) use ($destinatario): void {
                $this->aplicarEstado($notificacion, $destinatario);
            });
    }

    public function refrescarEstado(NotificacionFalta $notificacion): NotificacionFalta
    {
        $notificacion->loadMissing('destinatario');
        if ($notificacion->destinatario !== null) {
            $this->aplicarEstado($notificacion, $notificacion->destinatario);
        }

        return $notificacion;
    }

    public function recalcularEstados(): void
    {
        NotificacionFalta::query()
            ->with('destinatario')
            ->orderBy('id')
            ->chunkById(100, function ($lote): void {
                foreach ($lote as $notificacion) {
                    if ($notificacion->destinatario === null) {
                        continue;
                    }

                    $this->aplicarEstado($notificacion, $notificacion->destinatario);
                }
            });
    }

    private function aplicarEstado(NotificacionFalta $notificacion, User $destinatario): void
    {
        if ($notificacion->fecha_falta === null) {
            return;
        }

        $valor = $this->tieneJustificacionPorMarcado($destinatario, $notificacion->fecha_falta);
        if ($notificacion->tiene_justificacion_aprobada !== $valor) {
            $notificacion->update(['tiene_justificacion_aprobada' => $valor]);
        }
    }

    /**
     * @param  array{destinatario_id: string, fecha_falta: string, tipo_marcacion: string, notas?: ?string}  $datos
     */
    public function crear(User $emitidoPor, array $datos, ?User $decano = null): NotificacionFalta
    {
        $decano ??= $this->decanoActivo();
        if ($decano === null) {
            throw ValidationException::withMessages([
                'destinatario_id' => 'No hay un Decano activo configurado en el sistema.',
            ]);
        }

        $destinatario = User::query()->findOrFail($datos['destinatario_id']);
        $fecha = \Carbon\Carbon::parse($datos['fecha_falta'])->startOfDay();
        $tipo = $datos['tipo_marcacion'];

        if (! in_array($tipo, NotificacionFaltaMarcacion::valores(), true)) {
            throw ValidationException::withMessages([
                'tipo_marcacion' => 'Tipo de marcación no válido.',
            ]);
        }

        $delegado = $emitidoPor->id !== $decano->id
            && $emitidoPor->rol !== AppRole::Decano
            && $emitidoPor->rol !== AppRole::Superusuario;

        if ($delegado) {
            $titular = DelegacionDecano::decanoTitular($emitidoPor, CapabilityType::EmitirNotificacionesFaltas);
            if ($titular !== null) {
                $decano = $titular;
            }
        }

        if ($this->yaRegistrada($destinatario->id, $fecha, $tipo)) {
            throw ValidationException::withMessages([
                'fecha_falta' => $this->mensajeDuplicada($tipo, $fecha),
            ]);
        }

        try {
            return DB::transaction(function () use ($destinatario, $decano, $emitidoPor, $fecha, $tipo, $delegado, $datos): NotificacionFalta {
                $codigo = NotificacionFaltaCodigo::siguiente();

                return NotificacionFalta::query()->create([
                    'destinatario_id' => $destinatario->id,
                    'decano_id' => $decano->id,
                    'emitido_por_id' => $emitidoPor->id,
                    'fecha_falta' => $fecha,
                    'tipo_marcacion' => $tipo,
                    'codigo_oficio' => $codigo,
                    'emitido_delegado' => $delegado,
                    'tiene_justificacion_aprobada' => $this->tieneJustificacionPorMarcado($destinatario, $fecha),
                    'notas' => $datos['notas'] ?? null,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'fecha_falta' => $this->mensajeDuplicada($tipo, $fecha),
            ]);
        }
    }

    private function yaRegistrada(string $destinatarioId, \Carbon\CarbonInterface $fecha, string $tipo): bool
    {
        return NotificacionFalta::query()
            ->where('destinatario_id', $destinatarioId)
            ->whereDate('fecha_falta', $fecha->toDateString())
            ->where('tipo_marcacion', $tipo)
            ->exists();
    }

    private function mensajeDuplicada(string $tipo, \Carbon\CarbonInterface $fecha): string
    {
        $marcacion = NotificacionFaltaMarcacion::enOracion($tipo);

        return "Ya existe una notificación de {$marcacion} para este colaborador el {$fecha->format('d/m/Y')}. No se registra otra con la misma marcación y la misma fecha.";
    }

    public function cargoColaborador(User $user): string
    {
        return match ($user->rol) {
            AppRole::Docente => 'Docente',
            AppRole::Administrativo => 'Administrativo',
            AppRole::Mantenimiento => 'Código de trabajo',
            default => $user->rol->label(),
        };
    }

    public function carreraOUnidad(User $user): string
    {
        $carrera = Carreras::label((string) ($user->carrera ?? ''));

        if ($user->rol === AppRole::Docente && $carrera !== '—') {
            return $carrera;
        }

        if ($carrera !== '—') {
            return $carrera;
        }

        return match ($user->rol) {
            AppRole::Administrativo => 'Departamento administrativo',
            AppRole::Mantenimiento => 'Unidad de mantenimiento',
            default => '—',
        };
    }
}
