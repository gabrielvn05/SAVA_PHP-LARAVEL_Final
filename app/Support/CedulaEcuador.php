<?php

namespace App\Support;

class CedulaEcuador
{
    public static function containsLetters(string $raw): bool
    {
        return (bool) preg_match('/[a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', $raw);
    }

    public static function normalizeDigits(string $raw): string
    {
        return substr(preg_replace('/\D/', '', $raw) ?? '', 0, 10);
    }

    public static function isValid(string $cedula): bool
    {
        if (strlen($cedula) !== 10 || ! ctype_digit($cedula)) {
            return false;
        }

        if (self::isTriviallyInvalid($cedula)) {
            return false;
        }

        $provincia = (int) substr($cedula, 0, 2);
        if ($provincia < 1 || ($provincia > 24 && $provincia !== 30)) {
            return false;
        }

        $tercerDigito = (int) $cedula[2];
        if ($tercerDigito >= 6) {
            return false;
        }

        return self::checkDigitValid($cedula);
    }

    private static function isTriviallyInvalid(string $cedula): bool
    {
        if (preg_match('/^(\d)\1{9}$/', $cedula)) {
            return true;
        }

        if (count(array_unique(str_split($cedula))) <= 2) {
            return true;
        }

        if (preg_match('/^(\d{3})\1{2}\d?$/', $cedula)) {
            return true;
        }

        return in_array($cedula, ['1234567890', '0123456789', '1231231231'], true);
    }

    private static function checkDigitValid(string $cedula): bool
    {
        $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $suma = 0;

        for ($i = 0; $i < 9; $i++) {
            $valor = (int) $cedula[$i] * $coeficientes[$i];
            if ($valor >= 10) {
                $valor -= 9;
            }
            $suma += $valor;
        }

        $digitoEsperado = (10 - ($suma % 10)) % 10;

        return $digitoEsperado === (int) $cedula[9];
    }
}
