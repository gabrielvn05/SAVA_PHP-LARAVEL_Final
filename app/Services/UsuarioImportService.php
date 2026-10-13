<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\User;
use App\Support\AssignableAppRoles;
use App\Support\Carreras;
use App\Support\InstitutionalEmail;
use App\Support\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\Hash;

class UsuarioImportService
{
    /** @return array{created: int, errors: list<string>} */
    public function importFromCsv(string $csvContents): array
    {
        $lines = preg_split('/\R/', trim($csvContents)) ?: [];
        if ($lines === []) {
            return ['created' => 0, 'errors' => ['El archivo CSV está vacío.']];
        }

        $header = str_getcsv(array_shift($lines));
        $map = $this->columnMap($header);
        if ($map === null) {
            return ['created' => 0, 'errors' => ['Encabezados inválidos. Use: email,nombres,apellidos,cedula,celular,carrera,rol (opcional).']];
        }

        $created = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line);
            $lineNumber = $index + 2;
            $result = $this->createFromRow($row, $map, $lineNumber);
            if ($result === true) {
                $created++;
            } else {
                $errors[] = $result;
            }
        }

        return ['created' => $created, 'errors' => $errors];
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>|null
     */
    private function columnMap(array $header): ?array
    {
        $normalized = array_map(static fn (?string $col): string => strtolower(trim((string) $col)), $header);
        $required = ['email', 'nombres', 'apellidos', 'cedula', 'celular', 'carrera'];
        $map = [];

        foreach ($required as $column) {
            $pos = array_search($column, $normalized, true);
            if ($pos === false) {
                return null;
            }
            $map[$column] = $pos;
        }

        $rolPos = array_search('rol', $normalized, true);
        if ($rolPos !== false) {
            $map['rol'] = $rolPos;
        }

        return $map;
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     * @return true|string
     */
    private function createFromRow(array $row, array $map, int $lineNumber): bool|string
    {
        $email = strtolower(trim((string) ($row[$map['email']] ?? '')));
        $nombres = trim((string) ($row[$map['nombres']] ?? ''));
        $apellidos = trim((string) ($row[$map['apellidos']] ?? ''));
        $cedula = preg_replace('/\D/', '', (string) ($row[$map['cedula']] ?? '')) ?? '';
        $celular = preg_replace('/[^\d+]/', '', ltrim((string) ($row[$map['celular']] ?? ''), '+')) ?? '';
        $carrera = trim((string) ($row[$map['carrera']] ?? ''));
        $rolRaw = isset($map['rol']) ? trim((string) ($row[$map['rol']] ?? '')) : '';
        $rol = $rolRaw !== '' ? AppRole::tryFrom($rolRaw) : AppRole::Administrativo;

        if ($email === '' || $nombres === '' || $apellidos === '' || $cedula === '' || $celular === '' || $carrera === '') {
            return "Línea {$lineNumber}: faltan datos obligatorios.";
        }

        if (! InstitutionalEmail::isAllowed($email)) {
            return "Línea {$lineNumber}: correo no permitido ({$email}).";
        }

        if (User::query()->where('email', $email)->exists()) {
            return "Línea {$lineNumber}: el correo {$email} ya existe.";
        }

        if (strlen($cedula) < 10 || strlen($cedula) > 13) {
            return "Línea {$lineNumber}: cédula inválida.";
        }

        if (! Carreras::isValid($carrera)) {
            return "Línea {$lineNumber}: carrera inválida ({$carrera}).";
        }

        if ($rol === null || ! AssignableAppRoles::canAssign($rol)) {
            return "Línea {$lineNumber}: rol inválido o no permitido.";
        }

        $tempPassword = TemporaryPasswordGenerator::generate();

        User::query()->create([
            'email' => $email,
            'password' => Hash::make($tempPassword),
            'nombres' => $nombres,
            'apellidos' => $apellidos,
            'cedula' => $cedula,
            'celular' => $celular,
            'carrera' => $carrera,
            'rol' => $rol,
            'activo' => true,
            'email_verified_at' => now(),
            'force_password_change' => true,
        ]);

        return true;
    }
}
