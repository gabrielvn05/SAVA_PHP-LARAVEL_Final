@extends('layouts.app')

@section('title', 'Firmar oficio')

@php
    use App\Services\SolicitudOficioFirmadoService;
    use App\Support\SolicitudAdjuntos;

    $firmado = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud);
    $codigo = ($solicitud->detalle ?? [])['codigo_tramite'] ?? $solicitud->id;
@endphp

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Firmar oficio del trámite</h1>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                Trámite <strong>{{ $codigo }}</strong> · {{ $solicitud->tipo->label() }}
            </p>
        </div>
        <div class="page-header__actions">
            <a href="{{ route('solicitudes.edit', $solicitud) }}" class="btn btn--secondary btn--sm">Editar datos</a>
        </div>
    </header>

    <div class="wizard-progress" aria-hidden="true">
        <div class="wizard-progress__step is-done"></div>
        <div class="wizard-progress__step is-done"></div>
    </div>

    <p class="field-hint" style="margin: 0;">
        Paso 2 de 2: firma tu oficio en el navegador y luego envíalo a Secretaría. No hace falta guardar borrador solo para firmar.
    </p>

    @if($firmado)
        <p class="alert alert--success" style="margin: 0;">
            Oficio firmado: <strong>{{ $firmado['nombre'] ?? 'Documento' }}</strong>
            · <a href="{{ SolicitudAdjuntos::urlVista($solicitud, $firmado['path']) }}" target="_blank" rel="noopener">Ver</a>
        </p>
        <form method="POST" action="{{ route('solicitudes.enviar-secretaria', $solicitud) }}" class="row" style="gap: 0.5rem; flex-wrap: wrap;">
            @csrf
            <button type="submit" class="btn btn--primary">Enviar a Secretaría</button>
            <span class="field-hint" style="margin: 0; align-self: center;">La solicitud pasará a revisión de Secretaría.</span>
        </form>
    @endif

    <article
        class="card stack"
        data-oficio-firma-root
        data-pdf-url="{{ $pdfFirmaUrl }}"
        style="gap: 1rem;"
    >
        <div>
            <h2 class="solicitud-section-title" style="margin: 0;">Firma en el navegador</h2>
            <p class="field-hint" style="margin: 0.35rem 0 0;">
                1) Dibuja tu firma. 2) Haz clic en el documento donde debe aparecer. 3) Pulsa «Aplicar firma al oficio».
            </p>
        </div>

        <div class="row" style="gap: 0.5rem; flex-wrap: wrap;">
            <a href="{{ $oficioDescargarUrl }}" class="btn btn--secondary btn--sm" download>Opción: descargar PDF/Word</a>
            @if($oficioUsaDocx ?? false)
                <a href="{{ $oficioDocxUrl }}" class="btn btn--secondary btn--sm" download>Descargar Word</a>
            @endif
        </div>

        <label class="field">
            <span class="field__label">Tu firma (dibuja con mouse o dedo)</span>
            <canvas data-firma-pad width="420" height="130" style="width: 100%; max-width: 420px; border: 1px solid var(--color-border); border-radius: var(--radius-sm); touch-action: none;"></canvas>
            <button type="button" class="btn btn--ghost btn--sm" data-firma-clear style="margin-top: 0.35rem;">Limpiar firma</button>
        </label>

        <div class="firma-pdf-positioner">
            <p class="field-hint" style="margin: 0 0 0.5rem; color: #fff; padding: 0 1rem;">Haz clic en el documento para ubicar la firma (última página)</p>
            <button type="button" class="firma-pdf-positioner__page" data-firma-page>
                <canvas class="firma-pdf-positioner__canvas" data-firma-page-canvas></canvas>
                <span class="firma-pdf-positioner__mark" data-firma-mark hidden>Firma aquí</span>
            </button>
        </div>

        <p class="field-hint" data-firma-status style="margin: 0; color: var(--color-danger);"></p>

        <form method="POST" action="{{ route('solicitudes.firmar-navegador', $solicitud) }}" data-firma-form enctype="multipart/form-data">
            @csrf
            <input type="file" name="firma_imagen" data-firma-file-input hidden accept="image/png">
            <input type="hidden" name="firma_page" value="1" data-firma-page-input>
            <input type="hidden" name="firma_x" value="75" data-firma-x-input>
            <input type="hidden" name="firma_y" value="88" data-firma-y-input>
            <button type="submit" class="btn btn--primary">Aplicar firma al oficio</button>
        </form>

        <details>
            <summary class="field__label" style="cursor: pointer;">Alternativa: certificado .p12 (no se guarda)</summary>
            <form method="POST" action="{{ route('solicitudes.firmar-oficio-p12', $solicitud) }}" enctype="multipart/form-data" class="stack" style="gap: 0.65rem; margin-top: 0.5rem;" data-no-loading>
                @csrf
                <input type="file" name="p12" accept=".p12,.pfx" required class="field__input">
                <input type="password" name="p12_password" required class="field__input" placeholder="Contraseña del certificado" autocomplete="off">
                <button type="submit" class="btn btn--secondary btn--sm">Firmar con .p12</button>
            </form>
        </details>
    </article>
</section>
@endsection
