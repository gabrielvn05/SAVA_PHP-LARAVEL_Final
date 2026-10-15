@extends('layouts.app')

@section('title', 'Firmar oficio')

@php
    use App\Services\SolicitudOficioFirmadoService;
    use App\Support\SolicitudAdjuntos;

    $firmado = SolicitudOficioFirmadoService::oficioFirmadoEnAnexos($solicitud);
    $codigo = ($solicitud->detalle ?? [])['codigo_tramite'] ?? $solicitud->id;
    $mostrarSelector = ! $firmado && empty($modo);
@endphp

@section('content')
{{-- firma-ui: selector-p12-manual v2 (sin rúbrica dibujada) --}}
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

    @if(session('success'))
        <p class="alert alert--success" style="margin: 0;">{{ session('success') }}</p>
    @endif
    @if(session('error'))
        <p class="alert alert--danger" style="margin: 0;">{{ session('error') }}</p>
    @endif
    @if($errors->any())
        <div class="alert alert--danger stack" style="margin: 0; gap: 0.35rem;">
            <p style="margin: 0;"><strong>No se pudo aplicar la firma:</strong></p>
            <ul style="margin: 0; padding-left: 1.2rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($firmado)
        <div id="vista-firmado">
            @include('solicitudes._firmar-documento-firmado', ['solicitud' => $solicitud, 'firmado' => $firmado])
        </div>
    @elseif($mostrarSelector)
        <p class="field-hint" style="margin: 0;">
            Paso 2 de 2: elige cómo firmar el oficio. Luego podrás enviarlo a Secretaría.
        </p>

        <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem;">
            <a href="{{ route('solicitudes.firmar', ['solicitud' => $solicitud, 'modo' => 'p12']) }}" class="wizard-tipo-card" style="text-decoration: none; color: inherit;">
                <h2 class="wizard-tipo-card__title">Firmar con certificado .p12</h2>
                <p class="wizard-tipo-card__desc">
                    Sube tu archivo .p12, ingresa la contraseña y el sistema genera el oficio firmado electrónicamente.
                </p>
            </a>
            <a href="{{ route('solicitudes.firmar', ['solicitud' => $solicitud, 'modo' => 'manual']) }}" class="wizard-tipo-card" style="text-decoration: none; color: inherit;">
                <h2 class="wizard-tipo-card__title">Subir documento firmado manualmente</h2>
                <p class="wizard-tipo-card__desc">
                    Descarga el oficio en PDF o Word, fírmalo fuera del sistema e importa el archivo ya firmado.
                </p>
            </a>
        </div>
    @elseif($modo === 'p12' || old('_return_modo') === 'p12')
        @include('solicitudes._firmar-metodo-p12', ['modo' => 'p12'])
    @elseif($modo === 'manual')
        @include('solicitudes._firmar-metodo-manual')
    @endif
</section>
@endsection
