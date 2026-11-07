@extends('layouts.app')

@section('title', 'Accesos y sesiones')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Accesos y sesiones</h1>
            <p class="page-header__subtitle">
                Ingresos correctos, intentos fallidos y cierre de la sesión de cualquier cuenta.
            </p>
        </div>
    </header>

    <article class="card stack">
        <h2 style="margin: 0; font-size: 1rem;">Cerrar la sesión de una cuenta</h2>
        <form method="POST" action="{{ route('admin.accesos.cerrar') }}" class="sesion-cerrar" id="sesion-cerrar-form">
            @csrf
            <div class="field sesion-cerrar__buscar">
                <label class="field__label" for="sesion-cuenta-buscar">Cuenta</label>
                @include('partials.user-search', [
                    'id' => 'sesion-cuenta-buscar',
                    'name' => 'usuario_id',
                    'people' => $cuentas,
                    'selected' => old('usuario_id'),
                    'required' => true,
                    'placeholder' => 'Buscar por nombre, correo o cédula…',
                    'hint' => '#sesion-cuenta-ayuda',
                    'hintEmpty' => 'Escriba para filtrar y elija la cuenta. La persona deberá volver a identificarse.',
                    'hintSelected' => 'Se cerrará la sesión de {nombre} · {email} · {estado}.',
                ])
            </div>
            <button type="submit" class="btn btn--danger">Cerrar sesión</button>
        </form>
        <p class="field-hint" id="sesion-cuenta-ayuda" style="margin: 0;">Escriba para filtrar y elija la cuenta. La persona deberá volver a identificarse.</p>
    </article>

    <article class="card stack">
        <h2 style="margin: 0; font-size: 1rem;">Sesiones abiertas</h2>
        @if($usaSesionesEnBase)
            @if($sesiones->isEmpty())
                <p class="field-hint" style="margin: 0;">No hay sesiones abiertas en este momento.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Última actividad</th>
                                <th>IP</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sesiones as $sesion)
                                <tr>
                                    <td data-label="Usuario">{{ $sesion->user->nombreCompleto() }}</td>
                                    <td data-label="Rol">{{ $sesion->user->rol->label() }}</td>
                                    <td data-label="Última actividad">{{ $sesion->last_activity->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                    <td data-label="IP">{{ $sesion->ip_address ?: '—' }}</td>
                                    <td data-label="Acciones">
                                        @if($sesion->user->id !== auth()->id())
                                            <form method="POST" action="{{ route('admin.accesos.cerrar') }}" style="margin: 0;">
                                                @csrf
                                                <input type="hidden" name="usuario_id" value="{{ $sesion->user->id }}">
                                                <button type="submit" class="btn btn--danger btn--sm">Cerrar</button>
                                            </form>
                                        @else
                                            Sesión actual
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            <p class="field-hint" style="margin: 0;">El listado de sesiones abiertas aparece cuando las sesiones se guardan en la base de datos. El cierre manual de arriba sigue disponible.</p>
        @endif
    </article>

    <article class="card stack">
        <h2 style="margin: 0; font-size: 1rem;">Historial de accesos</h2>
        <form method="GET" action="{{ route('admin.accesos.index') }}" class="filter-bar">
            <label class="field">
                <span class="field__label">Resultado</span>
                <select name="resultado" class="field__input">
                    <option value="">Todos</option>
                    @foreach($resultados as $resultado)
                        <option value="{{ $resultado->value }}" @selected(($filtros['resultado'] ?? '') === $resultado->value)>{{ $resultado->label() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">
                <span class="field__label">Desde</span>
                <input type="date" name="desde" class="field__input" value="{{ $filtros['desde'] ?? '' }}">
            </label>
            <label class="field">
                <span class="field__label">Hasta</span>
                <input type="date" name="hasta" class="field__input" value="{{ $filtros['hasta'] ?? '' }}">
            </label>
            <label class="field filter-bar__wide">
                <span class="field__label">Buscar</span>
                <input type="search" name="q" class="field__input" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre, correo, cédula o IP">
            </label>
            <button type="submit" class="btn btn--secondary btn--sm">Filtrar</button>
            @if(collect($filtros)->filter(fn ($valor) => filled($valor))->isNotEmpty())
                <a href="{{ route('admin.accesos.index') }}" class="btn btn--ghost btn--sm">Limpiar</a>
            @endif
        </form>

        @if($eventos->isEmpty())
            <p class="field-hint" style="margin: 0;">No hay accesos con los filtros seleccionados.</p>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cuenta</th>
                            <th>Resultado</th>
                            <th>Canal</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($eventos as $evento)
                            <tr>
                                <td data-label="Fecha">{{ $evento->occurred_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                                <td data-label="Cuenta">
                                    {{ $evento->user?->nombreCompleto() ?: ($evento->email ?: 'Correo no registrado') }}
                                    @if($evento->resultado === \App\Enums\AccesoResultado::SesionCerrada && $evento->actor)
                                        <span class="field-hint" style="display: block;">Por {{ $evento->actor->nombreCompleto() }}</span>
                                    @elseif($evento->email)
                                        <span class="field-hint" style="display: block;">{{ $evento->email }}</span>
                                    @endif
                                </td>
                                <td data-label="Resultado">
                                    <span class="badge {{ $evento->resultado->badge() }}">{{ $evento->resultado->label() }}</span>
                                </td>
                                <td data-label="Canal">{{ $evento->canal === 'microsoft' ? 'Microsoft 365' : ($evento->canal === 'sistema' ? 'Superusuario' : 'Clave') }}</td>
                                <td data-label="IP">{{ $evento->ip_address ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $eventos->links() }}
        @endif
    </article>
</section>
@endsection
