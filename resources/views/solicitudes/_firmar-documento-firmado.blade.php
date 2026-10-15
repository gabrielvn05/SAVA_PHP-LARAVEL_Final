@php
    use App\Support\SolicitudAdjuntos;

    $firmaMeta = ($solicitud->detalle ?? [])['firma_docente'] ?? null;
    $urlFirmado = SolicitudAdjuntos::urlVista($solicitud, $firmado['path']);
    $nombreFirmado = $firmado['nombre'] ?? 'oficio-firmado.pdf';
    $extension = strtolower(pathinfo($nombreFirmado, PATHINFO_EXTENSION));
    $kindVisor = in_array($extension, ['png', 'jpg', 'jpeg'], true) ? 'image' : 'pdf';
    $puedeEnviar = $solicitud->estado === \App\Enums\SolicitudEstado::EnBorrador
        && auth()->id() === $solicitud->creado_por;
@endphp

<article class="card stack solicitud-preview" style="gap: 1rem;">
    <div>
        <h2 class="solicitud-section-title" style="margin: 0;">Vista previa del oficio firmado</h2>
        <p class="field-hint" style="margin: 0.35rem 0 0;">
            Revisa cómo quedó el documento. El <strong>PDF firmado se guarda</strong> en tu trámite; el archivo <strong>.p12 no se almacena</strong> en el servidor.
            @if(is_array($firmaMeta) && ($firmaMeta['tipo'] ?? '') === 'p12_documento' && ! empty($firmaMeta['titular']))
                Titular del certificado: {{ $firmaMeta['titular'] }}.
            @endif
        </p>
    </div>

    <x-doc-viewer
        title="Oficio firmado"
        :file-name="$nombreFirmado"
        :src="$urlFirmado"
        :download-href="$urlFirmado"
        :kind="$kindVisor"
    />

    @if($puedeEnviar)
        <form method="POST" action="{{ route('solicitudes.enviar-secretaria', $solicitud) }}" class="stack" style="gap: 0.5rem;">
            @csrf
            <button type="submit" class="btn btn--primary">Enviar a Secretaría</button>
            <p class="field-hint" style="margin: 0;">Al enviar, la solicitud pasará a revisión de Secretaría.</p>
        </form>
    @endif

    <p class="field-hint" style="margin: 0;">
        ¿Necesitas firmar de nuevo?
        <a href="{{ route('solicitudes.firmar', ['solicitud' => $solicitud, 'modo' => 'p12']) }}">Certificado .p12</a>
        ·
        <a href="{{ route('solicitudes.firmar', ['solicitud' => $solicitud, 'modo' => 'manual']) }}">Subir otro archivo</a>
    </p>
</article>
