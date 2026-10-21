<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\User;
use App\Support\AssignableAppRoles;
use App\Support\Carreras;
use App\Support\CedulaEcuador;
use App\Support\InstitutionalEmail;
use App\Support\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\Hash;

class UsuarioImportService
{
    /** @var list<string> */
    public const COLUMNAS = ['email', 'nombres', 'apellidos', 'cedula', 'celular', 'carrera', 'rol'];

    public const EMAIL_EJEMPLO = 'ejemplo.reemplazar@uleam.edu.ec';

    public function contenidoPlantilla(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fwrite($handle, "sep=,\n");
        fputcsv($handle, self::COLUMNAS);
        fputcsv($handle, [
            self::EMAIL_EJEMPLO,
            'Juan',
            'Pérez',
            '1712345675',
            '0991234567',
            'software',
            'docente',
        ]);
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }

    /** @return array{created: int, errors: list<string>} */
    public function importFromCsv(string $csvContents): array
    {
        $csvContents = preg_replace('/^\xEF\xBB\xBF/', '', $csvContents) ?? $csvContents;
        if (trim($csvContents) === '') {
            return ['created' => 0, 'errors' => ['El archivo CSV está vacío.']];
        }

        $lines = preg_split('/\R/', trim($csvContents)) ?: [];

        $headerLineNumber = 1;
        if ($lines !== [] && $this->isSepDirective($lines[0])) {
            array_shift($lines);
            $headerLineNumber = 2;
        }

        if ($lines === []) {
            return ['created' => 0, 'errors' => ['El archivo CSV está vacío.']];
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $header = str_getcsv(array_shift($lines), $delimiter);
        $map = $this->columnMap($header);
        if ($map === null) {
            return ['created' => 0, 'errors' => ['Encabezados inválidos. Descargue la plantilla oficial: email,nombres,apellidos,cedula,celular,carrera,rol (opcional).']];
        }

        $created = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line, $delimiter);
            $lineNumber = $index + $headerLineNumber + 1;
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

    private function isSepDirective(string $line): bool
    {
        return (bool) preg_match('/^sep=.$/i', trim($line));
    }

    private function detectDelimiter(string $headerLine): string
    {
        $comma = count(str_getcsv($headerLine, ','));
        $semicolon = count(str_getcsv($headerLine, ';'));

        return $semicolon > $comma ? ';' : ',';
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<string, int>  $map
     * @return true|string
     */
    private function createFromRow(array $row, array $map, int $lineNumber): bool|string
    {
        $email = strtolower(trim((string) ($row[$map['email']] ?? '')));
        if ($email === self::EMAIL_EJEMPLO) {
            return "Línea {$lineNumber}: elimine la fila de ejemplo de la plantilla y escriba los usuarios reales.";
        }
        $nombres = trim((string) ($row[$map['nombres']] ?? ''));
        $apellidos = trim((string) ($row[$map['apellidos']] ?? ''));
        $cedula = CedulaEcuador::normalizeDigits((string) ($row[$map['cedula']] ?? ''));
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

        if (! CedulaEcuador::isValid($cedula)) {
            return "Línea {$lineNumber}: cédula inválida.";
        }

        if (User::query()->where('cedula', $cedula)->exists()) {
            return "Línea {$lineNumber}: la cédula ya está registrada.";
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
