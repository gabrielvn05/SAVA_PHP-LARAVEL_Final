<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Oficio {{ $codigo }}</title>
    <style>
        @page { size: A4; margin: 20mm 14mm 22mm 28mm; }
        body { font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 11pt; color: #111; line-height: 1.45; margin: 0; padding: 24px 28px; min-height: 100vh; background: #fff; }
        .header { position: relative; min-height: 95px; margin-bottom: 18px; }
        .header__logo { position: absolute; left: 0; top: 0; max-width: 210px; max-height: 72px; }
        .header__facultad { position: absolute; right: 0; top: 4px; text-align: right; font-style: italic; font-size: 10pt; }
        .marca { position: fixed; top: 35%; left: 50%; transform: translate(-50%, -50%); opacity: 0.08; max-width: 420px; z-index: -1; }
        .meta { margin: 1rem 0; }
        .cuerpo { text-align: justify; margin: 0.75rem 0; }
        .firma { margin-top: 2.5rem; }
        .firma__nombre { font-weight: 700; margin: 0; }
        .firma__cargo { margin: 0.15rem 0 0; font-size: 10pt; }
    </style>
</head>
<body>
    <img src="{{ $marcaUleam }}" alt="" class="marca" aria-hidden="true">

    <header class="header">
        <img src="{{ $logoFacultad }}" alt="Facultad" class="header__logo">
        <div class="header__facultad">
            Facultad de Ciencias de la Vida y la Tecnología<br>
            (FACIVITEC)
        </div>
    </header>

    <div class="meta">
        <p><strong>Oficio N.º {{ $codigo }}</strong></p>
        <p>Manta, {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>
    </div>

    <p>Señor(a)<br>
    <strong>{{ $solicitud->creador->nombreCompleto() }}</strong><br>
    {{ $solicitud->creador->email }}</p>

    <p>De mi consideración:</p>

    <p class="cuerpo">
        Por medio del presente certifico que se ha registrado en el Sistema de Asistencia y Validaciones Académicas (SAVA)
        una solicitud de tipo <strong>{{ $solicitud->tipo->label() }}</strong> correspondiente al periodo del
        {{ $solicitud->fecha_inicio->format('d/m/Y') }} al {{ $solicitud->fecha_fin->format('d/m/Y') }},
        con el siguiente motivo: {{ $solicitud->motivo }}
    </p>

    @if(!empty($solicitud->detalle['destino']))
        <p class="cuerpo">Destino: {{ $solicitud->detalle['destino'] }}</p>
    @endif

    @if(!empty($solicitud->detalle['jornada']))
        <p class="cuerpo">Jornada: {{ $solicitud->detalle['jornada'] }}</p>
    @endif

    <p class="cuerpo">Estado actual del trámite: <strong>{{ $solicitud->estado->label() }}</strong>.</p>

    <p class="cuerpo">Sin otro particular, me suscribo.</p>

    <div class="firma">
        @if($decano)
            <p class="firma__nombre">{{ $decano->nombreCompleto() }}</p>
            <p class="firma__cargo">Decano(a) de Facultad</p>
        @else
            <p class="firma__cargo">Decano(a) de Facultad</p>
        @endif
    </div>
</body>
</html>
