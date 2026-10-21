<?php

namespace App\Services;

use App\Models\FirmaEcEnvio;
use App\Models\Solicitud;
use App\Models\User;
use App\Support\OficioNombreArchivo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FirmaEcService
{
    public function __construct(
        private readonly OficioPdfService $pdf,
        private readonly SolicitudOficioFirmadoService $oficioFirmado,
    ) {}

    public function configurado(): bool
    {
        return filled(config('firmaec.api_key')) && filled(config('firmaec.sistema'));
    }

    /**
     * @return array{envio: FirmaEcEnvio, enlace: string}
     */
    public function preparar(
        Solicitud $solicitud,
        User $usuario,
        int $pagina,
        float $xPorcentaje,
        float $yPorcentaje,
        int $tipoCertificado,
    ): array {
        if (! $this->configurado()) {
            throw ValidationException::withMessages([
                'firmaec' => 'Falta registrar SAVA en FirmaEC. Configura FIRMAEC_API_KEY y FIRMAEC_SISTEMA.',
            ]);
        }

        $cedula = preg_replace('/\D+/', '', (string) $usuario->cedula) ?? '';
        if (strlen($cedula) < 10) {
            throw ValidationException::withMessages([
                'firmaec' => 'Tu usuario no tiene una cédula válida. Complétala en el perfil antes de firmar.',
            ]);
        }

        $pdf = $this->pdf->generarParaFirma($solicitud);
        if (! is_string($pdf) || ! str_starts_with($pdf, '%PDF')) {
            throw ValidationException::withMessages([
                'firmaec' => 'No se pudo preparar el PDF del oficio para FirmaEC.',
            ]);
        }

        $nombre = 'sava-'.Str::lower((string) Str::ulid()).'.pdf';
        $token = $this->crearDocumento($cedula, $nombre, $pdf);
        $rect = $this->rectanguloA4($xPorcentaje, $yPorcentaje);

        $envio = FirmaEcEnvio::query()->create([
            'solicitud_id' => $solicitud->id,
            'user_id' => $usuario->id,
            'cedula' => $cedula,
            'nombre_documento' => $nombre,
            'estado' => 'pendiente',
            'pagina' => max(1, $pagina),
        ]);

        return [
            'envio' => $envio,
            'enlace' => $this->enlace($token, $rect, max(1, $pagina), $tipoCertificado),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recibirFirmado(array $payload): void
    {
        $nombre = trim((string) ($payload['nombreDocumento'] ?? ''));
        $envio = FirmaEcEnvio::query()->where('nombre_documento', $nombre)->first();
        if ($envio === null) {
            throw ValidationException::withMessages([
                'nombreDocumento' => 'El documento no corresponde a una firma iniciada en SAVA.',
            ]);
        }

        if ($envio->estado === 'firmado') {
            return;
        }

        if ($envio->created_at !== null && $envio->created_at->lt(now()->subMinutes(30))) {
            $envio->update([
                'estado' => 'error',
                'error' => 'La solicitud de firma expiró.',
            ]);

            throw ValidationException::withMessages([
                'nombreDocumento' => 'La solicitud de firma expiró. Vuelve a firmar desde SAVA.',
            ]);
        }

        $cedula = preg_replace('/\D+/', '', (string) ($payload['cedula'] ?? '')) ?? '';
        if ($cedula === '' || ! hash_equals($envio->cedula, $cedula)) {
            throw ValidationException::withMessages([
                'cedula' => 'La cédula no coincide con quien inició la firma.',
            ]);
        }

        $error = trim((string) ($payload['error'] ?? ''));
        if ($error !== '' && strtolower($error) !== 'null') {
            $envio->update(['estado' => 'error', 'error' => $error]);

            throw ValidationException::withMessages(['error' => $error]);
        }

        if (! filter_var($payload['firmasValidas'] ?? false, FILTER_VALIDATE_BOOL) || ! filter_var($payload['integridadDocumento'] ?? false, FILTER_VALIDATE_BOOL)) {
            $envio->update([
                'estado' => 'error',
                'error' => 'FirmaEC no confirmó la integridad de la firma.',
            ]);

            throw ValidationException::withMessages([
                'firmasValidas' => 'FirmaEC no confirmó la integridad de la firma.',
            ]);
        }

        $binario = base64_decode((string) ($payload['archivo'] ?? ''), true);
        if (! is_string($binario) || ! str_starts_with($binario, '%PDF')) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo devuelto por FirmaEC no es un PDF.',
            ]);
        }

        $certificado = is_array($payload['certificado'][0] ?? null) ? $payload['certificado'][0] : [];
        $solicitud = $envio->solicitud;
        if ($solicitud === null) {
            throw ValidationException::withMessages([
                'nombreDocumento' => 'El trámite ya no existe.',
            ]);
        }

        $this->oficioFirmado->guardarPdfFirmadoExterno($solicitud, $binario, OficioNombreArchivo::base($solicitud).'-firmado-firmaec.pdf', [
            'tipo' => 'firmaec',
            'formato' => 'pades',
            'titular' => (string) ($certificado['emitidoPara'] ?? ''),
            'serial' => (string) ($certificado['serial'] ?? ''),
            'entidad' => (string) ($certificado['entidadCertificadora'] ?? ''),
            'fecha' => (string) ($certificado['fechaFirma'] ?? now()->toIso8601String()),
            'pagina' => (string) $envio->pagina,
        ]);

        $envio->update([
            'estado' => 'firmado',
            'error' => null,
            'firmado_en' => now(),
        ]);
    }

    /**
     * @return array{llx: int, lly: int, urx: int, ury: int}
     */
    public function rectanguloA4(float $xPorcentaje, float $yPorcentaje): array
    {
        $ancho = 595.0;
        $alto = 842.0;
        $selloAncho = 170.0;
        $selloAlto = 60.0;
        $centroX = max(0.0, min(100.0, $xPorcentaje)) / 100 * $ancho;
        $centroY = $alto - (max(0.0, min(100.0, $yPorcentaje)) / 100 * $alto);
        $llx = (int) max(8, min($ancho - $selloAncho - 8, $centroX - ($selloAncho / 2)));
        $lly = (int) max(8, min($alto - $selloAlto - 8, $centroY - ($selloAlto / 2)));

        return [
            'llx' => $llx,
            'lly' => $lly,
            'urx' => $llx + (int) $selloAncho,
            'ury' => $lly + (int) $selloAlto,
        ];
    }

    private function crearDocumento(string $cedula, string $nombre, string $pdf): string
    {
        $url = rtrim((string) config('firmaec.base_url'), '/').'/servicio/documentos';

        try {
            $respuesta = Http::withHeaders([
                'X-API-KEY' => (string) config('firmaec.api_key'),
                'Accept' => 'text/plain, application/json',
            ])->timeout(60)->post($url, [
                'sistema' => (string) config('firmaec.sistema'),
                'cedula' => $cedula,
                'documentos' => [[
                    'nombre' => $nombre,
                    'documento' => base64_encode($pdf),
                ]],
            ]);
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'firmaec' => 'No se pudo contactar el servicio de FirmaEC. Intenta de nuevo en unos minutos.',
            ]);
        }

        if ($respuesta->status() === 403) {
            throw ValidationException::withMessages([
                'firmaec' => 'FirmaEC rechazó la clave del sistema. Revisa FIRMAEC_API_KEY y FIRMAEC_SISTEMA.',
            ]);
        }

        if (! $respuesta->successful()) {
            throw ValidationException::withMessages([
                'firmaec' => 'FirmaEC no aceptó el documento (respuesta '.$respuesta->status().').',
            ]);
        }

        return $this->extraerToken($respuesta->body());
    }

    private function extraerToken(string $cuerpo): string
    {
        $cuerpo = trim($cuerpo);
        if (str_starts_with($cuerpo, '{')) {
            $json = json_decode($cuerpo, true);
            if (is_array($json)) {
                foreach (['token', 'jwt'] as $clave) {
                    if (is_string($json[$clave] ?? null) && $json[$clave] !== '') {
                        return $json[$clave];
                    }
                }
            }
        }

        $cuerpo = trim($cuerpo, "\" \n\r\t");
        if (substr_count($cuerpo, '.') >= 2) {
            return $cuerpo;
        }

        throw ValidationException::withMessages([
            'firmaec' => 'FirmaEC no devolvió el token para abrir la aplicación.',
        ]);
    }

    /**
     * @param  array{llx: int, lly: int, urx: int, ury: int}  $rect
     */
    private function enlace(string $token, array $rect, int $pagina, int $tipoCertificado): string
    {
        $parametros = [
            'token' => $token,
            'tipo_certificado' => $tipoCertificado,
            'llx' => $rect['llx'],
            'lly' => $rect['lly'],
            'urx' => $rect['urx'],
            'ury' => $rect['ury'],
            'estampado' => (string) config('firmaec.estampado', 'information2'),
            'razon' => (string) config('firmaec.razon'),
            'pagina' => $pagina,
        ];

        if (config('firmaec.pruebas')) {
            $parametros['pre'] = 'true';
        }

        $sistema = rawurlencode((string) config('firmaec.sistema'));

        return 'firmaec://'.$sistema.'/firmar?'.http_build_query($parametros, '', '&', PHP_QUERY_RFC3986);
    }
}
