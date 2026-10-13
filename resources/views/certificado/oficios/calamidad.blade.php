@extends('certificado.partials.oficio-layout')

@section('asunto')
    Justificación de faltas por calamidad doméstica
@endsection

@section('contenido')
    <p class="cuerpo">De mi consideración:</p>

    <p class="cuerpo">
        Yo, <strong>{{ $nombreCompleto }}</strong>, portador de la cédula de identidad N° <strong>{{ $cedula }}</strong>,
        docente de la carrera de <strong>{{ $carrera }}</strong> de la {{ $facultadNombre }}, por medio del presente documento formal
        justifico mi ausencia a mis actividades académicas por motivo de calamidad doméstica, conforme a los siguientes datos:
    </p>

    <p class="cuerpo">
        Período de la falta: desde el día <strong>{{ $fechaInicio }}</strong> hasta el día <strong>{{ $fechaFin }}</strong>,
        lo que corresponde a un total de <strong>{{ $numeroDias }}</strong> días calendario de ausencia.
    </p>

    <div class="bloque-datos">
        <p><strong>Detalle de la calamidad doméstica</strong></p>
        <p>Tipo de calamidad: {{ $tipoCalamidad }}</p>
        <p>Nombre completo del familiar afectado: {{ $nombreFamiliar }}</p>
        <p>Parentesco (hasta segundo grado de consanguinidad o afinidad): {{ $parentesco }}</p>
        <p>Descripción del hecho: {{ $descripcionHecho }}</p>
        <p>Lugar donde ocurrió: {{ $lugarSuceso }}</p>
        <p>Fecha del hecho: {{ $fechaHecho }}</p>
    </div>

    <p class="cuerpo">
        Declaro bajo mi compromiso académico que la información aquí consignada es verídica. Me comprometo a reincorporarme a mis labores
        en la fecha de retorno indicada, así como a presentar al Decanato, dentro de los 8 días hábiles siguientes a mi retorno, los documentos
        de soporte que acrediten la calamidad (certificado de defunción, informe médico, constancia hospitalaria u otro según la normativa
        institucional), y a realizar las gestiones de nivelación con mis estudiantes según lo establecido.
    </p>
@endsection
