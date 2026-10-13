<div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.55;color:#1a2332;max-width:36rem">
    <h2 style="margin:0 0 12px;font-size:1.15rem">Notificación de asistencia (Talento Humano)</h2>
    <p>Hola <strong>{{ $fullName }}</strong>,</p>
    <p>
        Por datos del sistema biométrico de asistencia (Face ID) y el informe de <strong>Talento Humano</strong>,
        el Decanato le comunica que <strong>no consta su marcación</strong> correspondiente al hecho siguiente:
    </p>
    <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:0.95rem">
        <tr>
            <td style="padding:8px 10px;border:1px solid #dde3ec;background:#f8fafc;color:#5c6b7f;width:42%">Notificación Nº</td>
            <td style="padding:8px 10px;border:1px solid #dde3ec"><strong>{{ $codigo }}</strong></td>
        </tr>
        <tr>
            <td style="padding:8px 10px;border:1px solid #dde3ec;background:#f8fafc;color:#5c6b7f">Fecha</td>
            <td style="padding:8px 10px;border:1px solid #dde3ec">{{ $fechaFalta }}</td>
        </tr>
        <tr>
            <td style="padding:8px 10px;border:1px solid #dde3ec;background:#f8fafc;color:#5c6b7f">Marcación faltante</td>
            <td style="padding:8px 10px;border:1px solid #dde3ec">{{ $tipoMarcacion }}</td>
        </tr>
    </table>
    <p>
        Si aún no lo ha hecho, puede presentar la <strong>justificación correspondiente</strong> ante el Decanato
        mediante SAVA, dentro del plazo indicado en el Reglamento de Régimen Académico Interno de la ULEAM
        (2 días hábiles desde la emisión del oficio).
    </p>
    <p>
        <a href="{{ $loginUrl }}" style="display:inline-block;padding:10px 16px;background:#1e4f8a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600">Ingresar a SAVA</a>
        &nbsp;
        <a href="{{ $solicitudesUrl }}" style="color:#1e4f8a">Nueva solicitud de justificación</a>
    </p>
    @if($decanoNombre)
        <p style="color:#5c6b7f;font-size:0.9rem;margin-top:20px">
            Decanato FACIVITEC — {{ $decanoNombre }}
        </p>
    @endif
    <p style="color:#5c6b7f;font-size:0.85rem;margin-top:16px">
        Si ya presentó la justificación para esta fecha, puede considerar este mensaje como notificación cumplida.
    </p>
</div>
