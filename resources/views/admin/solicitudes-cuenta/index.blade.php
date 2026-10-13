@extends('layouts.app')

@section('title', 'Solicitudes de cuenta')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Solicitudes de cuenta</h1>
        </div>
    </header>

    <article class="card">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Correo</th>
                        <th>Nombre</th>
                        <th>Cédula</th>
                        <th>Carrera</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($solicitudes as $solicitud)
                        <tr>
                            <td>{{ $solicitud->email }}</td>
                            <td>{{ $solicitud->nombres }} {{ $solicitud->apellidos }}</td>
                            <td>{{ $solicitud->cedula }}</td>
                            <td>{{ \App\Support\Carreras::label($solicitud->carrera) }}</td>
                            <td>{{ $solicitud->rol_solicitado->label() }}</td>
                            <td>{{ $solicitud->status->label() }}</td>
                            <td>
                                @if($solicitud->status === \App\Enums\AccountRequestStatus::Pendiente)
                                    @if($puedeAprobar)
                                        <form method="POST" action="{{ route('admin.solicitudes-cuenta.aprobar', $solicitud) }}" style="display:inline">
                                            @csrf
                                            <button type="submit" class="btn btn--primary btn--sm">Aprobar</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.solicitudes-cuenta.rechazar', $solicitud) }}" class="stack" style="margin-top:0.5rem;">
                                        @csrf
                                        <input type="text" name="rechazo_comentario" placeholder="Motivo rechazo" class="field__input field__input--sm">
                                        <button type="submit" class="btn btn--danger btn--sm">Rechazar</button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>
</section>
@endsection
