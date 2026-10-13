@extends('certificado.partials.oficio-layout')

@section('saludo_destinatario')
    De mis consideraciones.
@endsection

@section('contenido')
    <p class="cuerpo">
        Yo, <strong>{{ $nombreCompleto }}</strong>, portador de la cédula de identidad N° <strong>{{ $cedula }}</strong>,
        docente de la <strong>{{ $carrera }}</strong> de la {{ $facultadNombre }}, por medio del presente documento formal justifico mi ausencia
        a mis actividades académicas por razones de salud, conforme a los siguientes datos:
    </p>

    <p class="cuerpo">
        Período de la falta: desde el día <strong>{{ $fechaInicio }}</strong> hasta el día <strong>{{ $fechaFin }}</strong>,
        lo que corresponde a un total de <strong>{{ $numeroDias }}</strong> días calendario de ausencia.
    </p>

    <p class="cuerpo">
        Atención médica recibida: fui atendido(a) en <strong>{{ $institucionMedica }}</strong>, por el doctor(a)
        <strong>{{ $medicoTratante }}</strong>, quien emitió el siguiente diagnóstico: <strong>{{ $diagnostico }}</strong>.
    </p>

    <p class="cuerpo">
        Declaro bajo mi compromiso académico que la información aquí consignada es verídica y me comprometo a reincorporarme a mis labores
        en la fecha de retorno indicada, así como a realizar las gestiones de nivelación con mis estudiantes según lo establecido por la
        normativa institucional.
    </p>
@endsection
