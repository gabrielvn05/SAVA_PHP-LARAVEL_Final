<div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.55;color:#1a2332;max-width:560px">
    <div style="background:#0c3d7a;color:#fff;padding:16px 20px;border-radius:8px 8px 0 0">
        <p style="margin:0;font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.85">SAVA · ULEAM</p>
        <h2 style="margin:6px 0 0;font-size:20px">
            {{ $aprobada ? 'Solicitud aprobada' : 'Solicitud rechazada' }}
        </h2>
    </div>
    <div style="border:1px solid #d8dee8;border-top:0;padding:20px;border-radius:0 0 8px 8px">
        <p style="margin:0 0 12px">Hola <strong>{{ $fullName }}</strong>,</p>
        <p style="margin:0 0 12px">
            @if($aprobada)
                Tu solicitud <strong>fue aprobada</strong> y el trámite quedó cerrado.
            @else
                Tu trámite <strong>no fue autorizado</strong>.
            @endif
        </p>
        <p style="margin:0 0 8px">
            <strong>Tipo:</strong> {{ $tipo }}<br>
            @if($codigo)
                <strong>Código:</strong> {{ $codigo }}<br>
            @endif
            <strong>Periodo:</strong> {{ $periodo }}<br>
            <strong>Motivo:</strong> {{ $motivo }}
        </p>
        @if($observaciones)
            <p style="margin:12px 0;padding:12px;background:#f6f8fb;border-radius:8px">
                <strong>Observaciones:</strong><br>
                {{ $observaciones }}
            </p>
        @endif
        <p style="margin:16px 0 0">
            Puedes consultar el detalle en<br>
            <a href="{{ $detalleUrl }}">{{ $detalleUrl }}</a>
        </p>
        <p style="margin:18px 0 0;color:#5c6b7f;font-size:13px">
            Este mensaje es informativo. Si no reconoces esta solicitud, contacta a Secretaría de la facultad.
        </p>
    </div>
</div>
