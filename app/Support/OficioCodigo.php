<?php

namespace App\Support;

use App\Enums\AppRole;
use App\Enums\SolicitudEstado;
use App\Enums\SolicitudTipo;
use App\Models\Solicitud;
use App\Models\User;

class OficioCodigo
{
    public const PREFIJO = 'FACIVITEC';

    public static function generar(User $solicitante, SolicitudTipo $tipo): string
    {
        $base = sprintf(
            '%s-%s-%s',
            self::PREFIJO,
            self::siglasRol($solicitante->rol),
            self::siglasTipo($tipo),
        );

        $numero = self::siguienteNumero($base);

        do {
            $codigo = sprintf('%s-%04d', $base, $numero);
            $numero++;
        } while (self::codigoExiste($codigo));

        return $codigo;
    }

    /**
     * El correlativo queda fijo al salir de borrador. Si el trámite ya tiene
     * un código que nadie envió, se conserva; si no, se toma el siguiente.
     */
    public static function confirmarAlEnviar(Solicitud $solicitud): string
    {
        $solicitud->loadMissing('creador');
        $codigo = (string) (($solicitud->detalle ?? [])['codigo_tramite'] ?? '');
        if ($codigo !== '' && ! self::codigoExiste($codigo)) {
            return $codigo;
        }

        return self::generar($solicitud->creador, $solicitud->tipo);
    }

    public static function siglasRol(AppRole $rol): string
    {
        return match ($rol) {
            AppRole::Docente, AppRole::Decano => 'DOC',
            AppRole::Mantenimiento => 'CDT',
            AppRole::Administrativo, AppRole::Secretaria, AppRole::Superusuario => 'ADM',
        };
    }

    public static function siglasTipo(SolicitudTipo $tipo): string
    {
        return match ($tipo) {
            SolicitudTipo::Permiso => 'PERM',
            SolicitudTipo::Justificacion => 'JUST',
            SolicitudTipo::Enfermedad => 'CERTMED',
            SolicitudTipo::Viaje => 'VISCAM',
            SolicitudTipo::CalamidadDomestica => 'CDOM',
            SolicitudTipo::FaltaMarcado => 'NMAR',
        };
    }

    private static function siguienteNumero(string $base): int
    {
        $maximo = 0;
        $prefijo = $base.'-';

        Solicitud::query()
            ->where('estado', '!=', SolicitudEstado::EnBorrador->value)
            ->where('detalle->codigo_tramite', 'like', $prefijo.'%')
            ->pluck('detalle')
            ->each(function (mixed $detalle) use ($prefijo, &$maximo): void {
                if (is_string($detalle)) {
                    $detalle = json_decode($detalle, true);
                }

                $codigo = is_array($detalle) ? (string) ($detalle['codigo_tramite'] ?? '') : '';
                if (! str_starts_with($codigo, $prefijo)) {
                    return;
                }

                $correlativo = substr($codigo, strlen($prefijo));
                if (preg_match('/^\d+$/', $correlativo)) {
                    $maximo = max($maximo, (int) $correlativo);
                }
            });

        return $maximo + 1;
    }

    private static function codigoExiste(string $codigo): bool
    {
        return Solicitud::query()
            ->where('estado', '!=', SolicitudEstado::EnBorrador->value)
            ->where('detalle->codigo_tramite', $codigo)
            ->exists();
    }

    public static function usaPlantillaInstitucional(SolicitudTipo $tipo): bool
    {
        return in_array($tipo, [
            SolicitudTipo::FaltaMarcado,
            SolicitudTipo::CalamidadDomestica,
            SolicitudTipo::Enfermedad,
            SolicitudTipo::Viaje,
            SolicitudTipo::Permiso,
            SolicitudTipo::Justificacion,
        ], true);
    }
}
