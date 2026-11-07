@extends('layouts.app')

@section('title', 'Delegaciones')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Delegaciones vigentes</h1>
            <p class="page-header__subtitle">
                Personas que actúan con una función del Decano y a nombre de quién queda la firma.
            </p>
        </div>
    </header>

    <article class="card">
        @if($delegaciones->isEmpty())
            <p class="field-hint" style="margin: 0;">No hay delegaciones vigentes.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Persona</th>
                            <th>Función</th>
                            <th>Firma a nombre de</th>
                            <th>Otorgada por</th>
                            <th>Desde</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($delegaciones as $delegacion)
                            @php
                                $clave = $delegacion->user_id.'|'.$delegacion->capability->value;
                                $titular = $titulares[$clave] ?? null;
                            @endphp
                            <tr>
                                <td data-label="Persona">
                                    {{ $delegacion->user?->nombreCompleto() }}
                                    <span class="field-hint" style="display: block;">{{ $delegacion->user?->rol->label() }} · {{ $delegacion->user?->email }}</span>
                                </td>
                                <td data-label="Función">{{ $delegacion->capability->label() }}</td>
                                <td data-label="Firma a nombre de">{{ $titular?->nombreCompleto() ?: '—' }}</td>
                                <td data-label="Otorgada por">{{ $delegacion->otorgadoPor?->nombreCompleto() ?: '—' }}</td>
                                <td data-label="Desde">{{ $delegacion->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </article>
</section>
@endsection
