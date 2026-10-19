<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Support\AssignableAppRoles;
use App\Support\Carreras;
use App\Support\CedulaEcuador;
use App\Support\InstitutionalEmail;

class AccountRequestValidator
{
    /** @return array{ok: true, data: array<string, string>}|array{ok: false, aviso: string} */
    public function validate(array $raw): array
    {
        $email = strtolower(trim($raw['email'] ?? ''));
        $nombres = $this->nombreEnMayusculas($raw['nombres'] ?? '');
        $apellidos = $this->nombreEnMayusculas($raw['apellidos'] ?? '');
        $cedulaRaw = trim((string) ($raw['cedula'] ?? ''));
        $cedula = CedulaEcuador::normalizeDigits($cedulaRaw);
        $celular = preg_replace('/[^\d+]/', '', ltrim($raw['celular'] ?? '', '+')) ?? '';
        $carrera = trim($raw['carrera'] ?? '');
        $rol = trim($raw['rol_solicitado'] ?? '') ?: AppRole::Administrativo->value;

        if ($email === '' || $nombres === '' || $apellidos === '' || $cedulaRaw === '' || $celular === '' || $carrera === '') {
            return ['ok' => false, 'aviso' => 'datos_incompletos'];
        }

        if (! $this->esNombrePermitido($nombres)) {
            return ['ok' => false, 'aviso' => 'nombres_invalidos'];
        }

        if (! $this->esNombrePermitido($apellidos)) {
            return ['ok' => false, 'aviso' => 'apellidos_invalidos'];
        }

        foreach (['declaracion_veraz', 'declaracion_terminos', 'declaracion_privacidad', 'acepto_terminos'] as $declaracion) {
            if (! filter_var($raw[$declaracion] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return ['ok' => false, 'aviso' => 'declaraciones_pendientes'];
            }
        }

        if (CedulaEcuador::containsLetters($cedulaRaw)) {
            return ['ok' => false, 'aviso' => 'cedula_letras'];
        }

        if (strlen($cedula) !== 10 || ! ctype_digit($cedula)) {
            return ['ok' => false, 'aviso' => 'cedula_formato'];
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'aviso' => 'correo_invalido'];
        }

        if (! InstitutionalEmail::isAllowed($email)) {
            return ['ok' => false, 'aviso' => 'correo_no_institucional'];
        }

        if (! Carreras::isValid($carrera)) {
            return ['ok' => false, 'aviso' => 'carrera_invalida'];
        }

        if (! AssignableAppRoles::canRequestPublicly($rol)) {
            return ['ok' => false, 'aviso' => 'rol_invalido'];
        }

        if (strlen($celular) < 9 || strlen($celular) > 15) {
            return ['ok' => false, 'aviso' => 'celular_invalido'];
        }

        return [
            'ok' => true,
            'data' => [
                'email' => $email,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'cedula' => $cedula,
                'celular' => $celular,
                'carrera' => $carrera,
                'jornada' => '',
                'rol_solicitado' => $rol,
            ],
        ];
    }

    private function nombreEnMayusculas(string $valor): string
    {
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

        return mb_strtoupper($valor, 'UTF-8');
    }

    private function esNombrePermitido(string $valor): bool
    {
        return preg_match('/^\p{L}+(?: \p{L}+)*$/u', $valor) === 1;
    }
}
