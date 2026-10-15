@php
    use App\Services\SolicitudOficioFirmadoService;
    use App\Support\SolicitudAdjuntos;

    $oficioFirmado = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud);
    $firmaMeta = ($solicitud->detalle ?? [])['firma_docente'] ?? null;
@endphp
<article class="card stack" style="gap: 0.75rem;">
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Oficio: descargar, firmar y subir</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            Usa la pantalla de firma para certificado .p12 o subida manual, y luego envía a Secretaría.
        </p>
    </div>

    <div class="row" style="gap: 0.5rem; flex-wrap: wrap;">
        <a href="{{ $oficioDescargarUrl }}" class="btn btn--secondary btn--sm" download>
            Descargar oficio (PDF si está disponible)
        </a>
        @if($oficioUsaDocx ?? false)
            <a href="{{ $oficioDocxUrl }}" class="btn btn--secondary btn--sm" download>
                Descargar Word (.docx)
            </a>
        @endif
        <a href="{{ $oficioPreviewUrl }}" class="btn btn--ghost btn--sm" target="_blank" rel="noopener">
            Ver vista previa
        </a>
    </div>

    @if($oficioFirmado)
        <p class="alert alert--success" style="margin: 0;">
            Oficio firmado cargado:
            <strong>{{ $oficioFirmado['nombre'] ?? 'Documento' }}</strong>
            @if(is_array($firmaMeta) && ($firmaMeta['tipo'] ?? '') === 'p12')
                (firma electrónica · {{ $firmaMeta['titular'] ?? 'certificado' }})
            @endif
            ·
            <a href="{{ SolicitudAdjuntos::urlVista($solicitud, $oficioFirmado['path']) }}" target="_blank" rel="noopener">Ver / descargar</a>
        </p>
    @endif

    @unless($soloDescarga ?? false)
        <a href="{{ route('solicitudes.firmar', $solicitud) }}" class="btn btn--primary btn--sm">Ir a firmar oficio</a>
    @endunless

</article>
