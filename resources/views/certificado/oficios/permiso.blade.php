@extends('certificado.partials.oficio-layout')

@section('asunto')
    Permiso de ausencia a horas o jornada laboral
@endsection

@section('contenido')
    <p class="cuerpo">De mi consideración:</p>

    <p class="cuerpo">
        Yo, <strong>{{ $nombreCompleto }}</strong>, portador de la cédula de identidad N° <strong>{{ $cedula }}</strong>,
        {{ strtolower($rolPersonal) }} de la {{ $facultadNombre }}, solicito permiso para ausentarme de mis actividades
        el día <strong>{{ $fechaPermiso }}</strong>, en el horario comprendido entre las
        <strong>{{ $horaInicioFalta }}</strong> y las <strong>{{ $horaFinFalta }}</strong>, por el siguiente motivo:
        <strong>{{ $motivoPermiso }}</strong>.
    </p>

    @if(filled($observacionesAdicionales))
        <p class="cuerpo">Observaciones adicionales: {{ $observacionesAdicionales }}</p>
    @endif

    <p class="cuerpo">
        Declaro que la información consignada es verídica y me comprometo a cumplir con las disposiciones institucionales
        aplicables al retorno de mis labores.
    </p>
@endsection
