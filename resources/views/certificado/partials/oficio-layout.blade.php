<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Oficio {{ $codigo }}</title>
    <style>
        @page { size: A4; margin: 20mm 14mm 22mm 28mm; }
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #111; line-height: 1.5; margin: 0; padding: 24px 32px; min-height: 100vh; background: #fff; }
        .meta { margin: 0 0 1.25rem; }
        .meta__codigo { font-weight: 700; margin: 0 0 0.5rem; }
        .destinatario { margin: 1rem 0; }
        .asunto { margin: 1rem 0; font-weight: 700; }
        .cuerpo { text-align: justify; margin: 0.75rem 0; }
        .bloque-datos { margin: 0.75rem 0 0.75rem 1rem; }
        .bloque-datos p { margin: 0.35rem 0; }
        .firma-solicitante { margin-top: 2rem; }
        .firma-solicitante p { margin: 0.2rem 0; }
        .firma-decano { margin-top: 2.5rem; }
        .firma-decano__nombre { font-weight: 700; margin: 0; }
        .oficio-membrete {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 0 0 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #0c3d7a;
        }
        .oficio-membrete img { max-height: 72px; width: auto; }
        .firma-bloque { position: relative; min-height: 8rem; }
        .oficio-sello {
            position: absolute;
            right: 0;
            top: 0;
            width: 140px;
            height: auto;
        }
    </style>
</head>
<body>
    <header class="oficio-membrete">
        <img src="{{ asset('templates/oficio-media/logo-facultad.png') }}" alt="FACIVITEC">
        <img src="{{ asset('templates/oficio-media/marca-uleam.png') }}" alt="ULEAM">
    </header>
    <p class="meta__codigo">OFICIO N° {{ $codigo }}</p>
    <p class="meta">{{ \App\Services\OficioDatosService::fechaLugarGeneracion() }}</p>

    <div class="destinatario">
        <p style="margin: 0;">Dr.<br>
        <strong>{{ $decano?->nombreCompleto() ?? 'Decano(a) de Facultad' }}</strong><br>
        Decano de la {{ $facultadNombre }}<br>
        @hasSection('saludo_destinatario')
            @yield('saludo_destinatario')
        @else
            Presente. -
        @endif
        </p>
    </div>

    @hasSection('asunto')
        <p class="asunto">Asunto: @yield('asunto')</p>
    @endif

    @yield('contenido')

    <div class="firma-solicitante firma-bloque">
        <p>Atentamente,</p>
        <p>Aprobado por:</p>
        <p>{{ $rolPersonal ?? 'Docente' }} de la {{ $facultadNombre }}</p>
        <p>Cédula: {{ $cedula }}</p>
        <p>Correo institucional: {{ $correo }}</p>
        <p>Fecha de generación del documento: {{ $fechaGeneracion }}</p>
        @if($mostrarSelloAutorizado ?? false)
            <img
                src="{{ asset('templates/oficio-media/sello-autorizado.jpg') }}"
                alt="Sello AUTORIZADO FACIVITEC"
                class="oficio-sello"
                width="140"
                height="140"
            >
        @endif
    </div>

    @if($decano)
        <div class="firma-decano">
            <p class="firma-decano__nombre">{{ $decano->nombreCompleto() }}</p>
            <p style="margin: 0.15rem 0 0;">Decano de la {{ $facultadNombre }}</p>
            <p style="margin: 0.15rem 0 0;">Correo institucional: {{ $decano->email }}</p>
        </div>
    @endif
</body>
</html>
