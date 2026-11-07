@extends('layouts.app')

@section('title', 'Cuentas por atender')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Cuentas por atender</h1>
            <p class="page-header__subtitle">
                {{ $totalInactivas }} inactivas, {{ $totalClaves }} con clave temporal y {{ $totalAltas }} altas pendientes.
            </p>
        </div>
    </header>

    <article class="card stack">
        <h2 style="margin: 0; font-size: 1rem;">Cuentas inactivas</h2>
        @if($inactivas->isEmpty())
            <p class="field-hint" style="margin: 0;">No hay cuentas inactivas.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Cédula</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inactivas as $usuario)
                            <tr>
                                <td data-label="Nombre">{{ $usuario->nombreCompleto() }}</td>
                                <td data-label="Correo">{{ $usuario->email }}</td>
                                <td data-label="Rol">{{ $usuario->rol->label() }}</td>
                                <td data-label="Cédula">{{ $usuario->cedula ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <a href="{{ route('admin.usuarios.index') }}" class="btn btn--secondary btn--sm">Ir a usuarios</a>
        @endif
    </article>

    <article class="card stack">
        <h2 style="margin: 0; font-size: 1rem;">Claves temporales sin cambiar</h2>
        @if($claves->isEmpty())
            <p class="field-hint" style="margin: 0;">Nadie tiene una clave temporal pendiente.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($claves as $usuario)
                            <tr>
                                <td data-label="Nombre">{{ $usuario->nombreCompleto() }}</td>
                                <td data-label="Correo">{{ $usuario->email }}</td>
                                <td data-label="Rol">{{ $usuario->rol->label() }}</td>
                                <td data-label="Estado">{{ $usuario->activo ? 'Activa' : 'Inactiva' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>

    <article class="card stack">
        <h2 style="margin: 0; font-size: 1rem;">Solicitudes de alta pendientes</h2>
        @if($altas->isEmpty())
            <p class="field-hint" style="margin: 0;">No hay solicitudes de cuenta pendientes.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Rol solicitado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($altas as $alta)
                            <tr>
                                <td data-label="Nombre">{{ $alta->nombres }} {{ $alta->apellidos }}</td>
                                <td data-label="Correo">{{ $alta->email }}</td>
                                <td data-label="Rol solicitado">{{ $alta->rol_solicitado?->label() ?? '—' }}</td>
                                <td data-label="Fecha">{{ $alta->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="btn btn--secondary btn--sm">Revisar solicitudes</a>
        @endif
    </article>
</section>
@endsection
