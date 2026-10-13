<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Models\NotificacionFalta;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\Carreras;
use App\Support\NotificacionFaltaCodigo;
use App\Support\NotificacionFaltaMarcacion;
use Carbon\CarbonInterface;
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

    public function tieneJustificacionAprobada(User $destinatario, CarbonInterface $fecha): bool
    {
        return Solicitud::query()
            ->where('creado_por', $destinatario->id)
            ->where('estado', SolicitudEstado::Aprobada)
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->exists();
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
                'tiene_justificacion_aprobada' => $this->tieneJustificacionAprobada($destinatario, $fecha),
                'notas' => $datos['notas'] ?? null,
            ]);
        });
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
