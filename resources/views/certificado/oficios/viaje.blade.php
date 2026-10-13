@extends('certificado.partials.oficio-layout')

@section('asunto')
    Justificación de faltas por visita técnica de campo
@endsection

@section('contenido')
    <p class="cuerpo">De mis consideraciones:</p>

    <p class="cuerpo">
        Yo, <strong>{{ $nombreCompleto }}</strong>, portador de la cédula de identidad N° <strong>{{ $cedula }}</strong>,
        {{ strtolower($rolPersonal) }} de la carrera de <strong>{{ $carrera }}</strong> de la {{ $facultadNombre }}, por medio del presente documento
        justifico de manera formal mi ausencia a mis actividades por motivo de visita técnica de campo, conforme a los siguientes datos:
    </p>

    <p class="cuerpo">
        Período de la falta: desde el día <strong>{{ $fechaInicio }}</strong> hasta el día <strong>{{ $fechaFin }}</strong>,
        lo que corresponde a un total de <strong>{{ $numeroDias }}</strong> días hábiles de ausencia.
    </p>

    <div class="bloque-datos">
        <p><strong>Detalles de la visita técnica de campo</strong></p>
        <p>Tipo: {{ $tipoViaje }}</p>
        <p>Nombre del lugar o institución de estudio: {{ $nombreEvento }}</p>
        <p>Lugar (ciudad, país): {{ $lugarEvento }}</p>
        <p>Fechas del evento o actividad: {{ $fechasEventoActividad }}</p>
        @if(filled($rolEspecifico))
            <p>Rol específico (si aplica): {{ $rolEspecifico }}</p>
        @endif
    </div>

    @if(filled($observacionesAdicionales))
        <p class="cuerpo">Observaciones adicionales: {{ $observacionesAdicionales }}</p>
    @endif

    <p class="cuerpo">
        Declaro bajo mi compromiso ético que la información aquí consignada es verídica. Me comprometo a reincorporarme a mis labores
        en la fecha de retorno indicada, así como a presentar al Decanato un informe de las actividades realizadas (incluyendo constancias,
        certificados o programa del evento) en un plazo no mayor a 8 días hábiles posteriores a mi retorno, y a realizar las gestiones de
        nivelación según lo establecido por la normativa institucional.
    </p>
@endsection
