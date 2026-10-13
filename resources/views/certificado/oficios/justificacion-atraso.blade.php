@extends('certificado.partials.oficio-layout')

@section('asunto')
    Justificación por atraso
@endsection

@section('contenido')
    <p class="cuerpo">De mi consideración:</p>

    <p class="cuerpo">
        Yo, <strong>{{ $nombreCompleto }}</strong>, portador de la cédula de identidad N° <strong>{{ $cedula }}</strong>,
        {{ strtolower($rolPersonal) }} de la {{ $facultadNombre }}, por medio del presente documento justifico mi atraso
        en la jornada del día <strong>{{ $fechaAtraso }}</strong>.
    </p>

    <div class="bloque-datos">
        <p>Hora establecida de llegada: <strong>{{ $horaLlegadaEstablecida }}</strong></p>
        <p>Hora de llegada (atraso): <strong>{{ $horaLlegadaReal }}</strong></p>
        <p>Motivo: <strong>{{ $motivoAtraso }}</strong></p>
    </div>

    @if(filled($observacionesAdicionales))
        <p class="cuerpo">Observaciones adicionales: {{ $observacionesAdicionales }}</p>
    @endif

    <p class="cuerpo">
        Declaro que la información aquí consignada es verídica y corresponde a los hechos ocurridos en la fecha indicada.
    </p>
@endsection
