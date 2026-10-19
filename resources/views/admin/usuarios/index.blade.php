@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
<section class="stack">
    <header class="page-header">
        <div class="page-header__text">
            <h1 class="page-header__title">Gestión de usuarios</h1>
        </div>
    </header>

    @if($puedeCrear && ! ($rutas['importCsv'] ?? false) && ! ($rutas['rol'] ?? false) && ! ($rutas['updateLegacy'] ?? false))
        <div class="alert alert--warning" role="alert">
            Faltan rutas nuevas de administración en el servidor. Suba <code>routes/web.php</code> actualizado y ejecute
            <code>php artisan route:clear</code>.
        </div>
    @endif

    @if($puedeCrear)
        <article class="card stack">
            <h2 style="margin: 0; font-size: 1.1rem;">Crear usuario interno</h2>
            <p class="field-hint" style="margin: 0;">Correo institucional ULEAM. Contraseña: más de 8 caracteres, mayúscula, minúscula, número y carácter especial.</p>
            <form method="POST" action="{{ route('admin.usuarios.store') }}" class="form-grid">
                @csrf
                <label class="field">
                    <span class="field__label">Correo *</span>
                    <input type="email" name="email" required class="field__input" value="{{ old('email') }}">
                </label>
                <label class="field">
                    <span class="field__label">Contraseña *</span>
                    <input id="admin_password" type="password" name="password" required minlength="9" class="field__input" autocomplete="new-password">
                    @include('partials.password-strength', ['inputId' => 'admin_password'])
                </label>
                <label class="field">
                    <span class="field__label">Nombres *</span>
                    <input type="text" name="nombres" required class="field__input" value="{{ old('nombres') }}">
                </label>
                <label class="field">
                    <span class="field__label">Apellidos *</span>
                    <input type="text" name="apellidos" required class="field__input" value="{{ old('apellidos') }}">
                </label>
                <label class="field">
                    <span class="field__label">Cédula de identidad *</span>
                    <input
                        type="text"
                        name="cedula"
                        id="admin-cedula-input"
                        required
                        class="field__input"
                        value="{{ old('cedula') }}"
                        inputmode="numeric"
                        maxlength="10"
                        pattern="\d{10}"
                        autocomplete="off"
                        aria-describedby="admin-cedula-hint"
                    >
                    <p class="field-hint" id="admin-cedula-hint" style="margin: 0.35rem 0 0;">
                        Ingrese los 10 dígitos numéricos de la cédula ecuatoriana, sin letras ni caracteres especiales.
                    </p>
                    @error('cedula')
                        <p class="field-hint" style="color: var(--color-danger);">{{ $message }}</p>
                    @enderror
                </label>
                <label class="field">
                    <span class="field__label">Rol *</span>
                    <select name="rol" required class="field__input">
                        @foreach($roles as $rol)
                            <option value="{{ $rol->value }}">{{ $rol->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="field field--full">
                    <button type="submit" class="btn btn--primary">Crear usuario</button>
                </div>
            </form>
            <script>
                (function () {
                    const input = document.getElementById('admin-cedula-input');
                    if (!input) {
                        return;
                    }

                    const sanitize = () => {
                        const digits = input.value.replace(/\D/g, '').slice(0, 10);
                        if (input.value !== digits) {
                            input.value = digits;
                        }
                    };

                    input.addEventListener('input', sanitize);
                    input.addEventListener('blur', sanitize);
                })();
            </script>
        </article>

        @if($rutas['importCsv'] ?? false)
            <article class="card stack">
                <h2 style="margin: 0; font-size: 1.1rem;">Importar usuarios (CSV)</h2>
                <p class="field-hint" style="margin: 0;">
                    Columnas: <code>email,nombres,apellidos,cedula,celular,carrera,rol</code> (rol opcional).
                </p>
                <form method="POST" action="{{ route('admin.usuarios.import-csv') }}" enctype="multipart/form-data" class="row" style="gap: 0.75rem; flex-wrap: wrap; align-items: flex-end;">
                    @csrf
                    <label class="field" style="flex: 1; min-width: 220px;">
                        <span class="field__label">Archivo CSV</span>
                        <input type="file" name="archivo_csv" accept=".csv,text/csv" required class="field__input">
                    </label>
                    <button type="submit" class="btn btn--secondary">Importar</button>
                </form>
            </article>
        @endif
    @endif

    <article class="card">
        <div class="table-wrap">
            <table class="data-table data-table--users">
                <colgroup>
                    <col style="width: 18%;">
                    <col style="width: 22%;">
                    <col style="width: 12%;">
                    <col style="width: 10%;">
                    @if($puedeDelegar)
                        <col style="width: 18%;">
                    @endif
                    <col style="width: 16%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        @if($puedeDelegar)
                            <th>Funciones</th>
                        @endif
                        <th class="data-table__cell--actions">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($usuarios as $usuario)
                        @php
                            $puedeEditar = $puedeCambiarRol
                                && $usuario->rol !== \App\Enums\AppRole::Superusuario
                                && ! (auth()->user()->rol === \App\Enums\AppRole::Decano && $usuario->rol === \App\Enums\AppRole::Superusuario);
                            $nombrePartes = preg_split('/\s+/u', trim($usuario->nombreCompleto())) ?: [];
                            $iniciales = mb_strtoupper(
                                mb_substr($nombrePartes[0] ?? 'U', 0, 1)
                                .mb_substr($nombrePartes[1] ?? ($nombrePartes[0] ?? ''), 0, 1)
                            );
                        @endphp
                        <tr>
                            <td data-label="Nombre">
                                <div class="user-table-identity">
                                    <span class="user-table-avatar" aria-hidden="true">{{ $iniciales }}</span>
                                    <span class="user-table-name">{{ $usuario->nombreCompleto() }}</span>
                                </div>
                            </td>
                            <td data-label="Correo"><span class="user-table-email">{{ $usuario->email }}</span></td>
                            <td data-label="Rol"><span class="user-role-pill">{{ $usuario->rol->label() }}</span></td>
                            <td data-label="Estado">
                                <span class="badge {{ $usuario->activo ? 'badge--success' : 'badge--danger' }}">
                                    {{ $usuario->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            @if($puedeDelegar)
                                <td class="data-table__cell--capabilities" data-label="Funciones">
                                    @php
                                        $propiasDelRol = array_map(
                                            static fn (\App\Enums\CapabilityType $capability): string => $capability->value,
                                            $usuario->rol->defaultCapabilities(),
                                        );
                                    @endphp
                                    @if($usuario->capabilities->isEmpty())
                                        <span class="field-hint" style="margin: 0;">—</span>
                                    @else
                                        <ul class="capability-list">
                                            @foreach($usuario->capabilities as $cap)
                                                <li>
                                                    {{ $cap->capability->label() }}
                                                    @if(! in_array($cap->capability->value, $propiasDelRol, true))
                                                        <span class="capability-list__tag">Delegada</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            @endif
                            <td class="data-table__cell--actions" data-label="Acciones">
                                @php
                                    $tieneMenuRolEstado = $puedeEditar && ($rutas['rol'] ?? false) && ($rutas['estado'] ?? false);
                                    $tieneMenuLegacy = $puedeEditar && ($rutas['updateLegacy'] ?? false);
                                    $puedeResetClave = ($esSuperusuario ?? false) && ($rutas['resetClave'] ?? false) && $usuario->rol !== \App\Enums\AppRole::Superusuario;
                                    $puedeDelegarFila = $puedeDelegar
                                        && $usuario->id !== auth()->id()
                                        && $usuario->rol !== \App\Enums\AppRole::Superusuario;
                                    $tieneAcciones = $tieneMenuRolEstado || $tieneMenuLegacy || $puedeResetClave || $puedeDelegarFila;
                                @endphp

                                @if($usuario->rol === \App\Enums\AppRole::Superusuario)
                                    <span class="user-actions-badge user-actions-badge--shield">
                                        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm0 2.18 6 2.25v4.66c0 3.83-2.55 7.43-6 8.56-3.45-1.13-6-4.73-6-8.56V6.43l6-2.25z"/></svg>
                                        Protegida
                                    </span>
                                @elseif(! $tieneAcciones)
                                    <span class="field-hint" style="margin: 0;">—</span>
                                @else
                                    <select
                                        class="user-action-picker"
                                        data-user-action-picker
                                        data-user-id="{{ $usuario->id }}"
                                        aria-label="Acciones para {{ $usuario->nombreCompleto() }}"
                                    >
                                        <option value="">Elegir acción…</option>
                                        @if($tieneMenuRolEstado)
                                            <option value="rol">Cambiar rol</option>
                                            <option value="estado">Cambiar estado</option>
                                        @elseif($tieneMenuLegacy)
                                            <option value="rol-estado">Cambiar rol y estado</option>
                                        @endif
                                        @if($puedeResetClave)
                                            <option value="reset-clave">Restablecer clave</option>
                                        @endif
                                        @if($puedeDelegarFila)
                                            <option value="delegar">Delegar capacidad</option>
                                        @endif
                                    </select>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </article>

    <div class="user-admin-form-store" aria-hidden="true">
        @foreach($usuarios as $usuario)
            @php
                $puedeEditarStore = $puedeCambiarRol
                    && $usuario->rol !== \App\Enums\AppRole::Superusuario
                    && ! (auth()->user()->rol === \App\Enums\AppRole::Decano && $usuario->rol === \App\Enums\AppRole::Superusuario);
                $tieneMenuRolEstadoStore = $puedeEditarStore && ($rutas['rol'] ?? false) && ($rutas['estado'] ?? false);
                $tieneMenuLegacyStore = $puedeEditarStore && ($rutas['updateLegacy'] ?? false);
                $puedeResetClaveStore = ($esSuperusuario ?? false) && ($rutas['resetClave'] ?? false) && $usuario->rol !== \App\Enums\AppRole::Superusuario;
                $puedeDelegarStore = $puedeDelegar
                    && $usuario->id !== auth()->id()
                    && $usuario->rol !== \App\Enums\AppRole::Superusuario;
            @endphp
                @if($tieneMenuRolEstadoStore)
                    <template
                        data-user-admin-template="{{ $usuario->id }}-rol"
                        data-modal-title="Cambiar rol"
                        data-modal-subtitle="{{ $usuario->nombreCompleto() }} · {{ $usuario->email }}"
                    >
                        <form method="POST" action="{{ route('admin.usuarios.rol', $usuario) }}" class="user-admin-modal-form stack" data-loading-label="Actualizando rol…">
                            @csrf
                            @method('PATCH')
                            <label class="field">
                                <span class="field__label">Nuevo rol</span>
                                <select name="rol" class="field__input" required>
                                    @foreach($roles as $rol)
                                        <option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>{{ $rol->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <div class="logout-modal__actions">
                                <button type="button" class="btn btn--ghost" data-user-admin-modal-close>Cancelar</button>
                                <button type="submit" class="btn btn--primary">Guardar rol</button>
                            </div>
                        </form>
                    </template>
                    <template
                        data-user-admin-template="{{ $usuario->id }}-estado"
                        data-modal-title="Cambiar estado"
                        data-modal-subtitle="{{ $usuario->nombreCompleto() }} · {{ $usuario->email }}"
                    >
                        <form method="POST" action="{{ route('admin.usuarios.estado', $usuario) }}" class="user-admin-modal-form stack" data-loading-label="Actualizando estado…">
                            @csrf
                            @method('PATCH')
                            <label class="field">
                                <span class="field__label">Estado de la cuenta</span>
                                <select name="activo" class="field__input" required>
                                    <option value="1" @selected($usuario->activo)>Activo</option>
                                    <option value="0" @selected(! $usuario->activo)>Inactivo</option>
                                </select>
                            </label>
                            <div class="logout-modal__actions">
                                <button type="button" class="btn btn--ghost" data-user-admin-modal-close>Cancelar</button>
                                <button type="submit" class="btn btn--primary">Guardar estado</button>
                            </div>
                        </form>
                    </template>
                @elseif($tieneMenuLegacyStore)
                    <template
                        data-user-admin-template="{{ $usuario->id }}-rol-estado"
                        data-modal-title="Cambiar rol y estado"
                        data-modal-subtitle="{{ $usuario->nombreCompleto() }} · {{ $usuario->email }}"
                    >
                        <form method="POST" action="{{ route('admin.usuarios.update', $usuario) }}" class="user-admin-modal-form stack" data-loading-label="Guardando cambios…">
                            @csrf
                            @method('PATCH')
                            <div class="form-grid form-grid--2">
                                <label class="field">
                                    <span class="field__label">Rol</span>
                                    <select name="rol" class="field__input" required>
                                        @foreach($roles as $rol)
                                            <option value="{{ $rol->value }}" @selected($usuario->rol === $rol)>{{ $rol->label() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="field">
                                    <span class="field__label">Estado</span>
                                    <select name="activo" class="field__input" required>
                                        <option value="1" @selected($usuario->activo)>Activo</option>
                                        <option value="0" @selected(! $usuario->activo)>Inactivo</option>
                                    </select>
                                </label>
                            </div>
                            <div class="logout-modal__actions">
                                <button type="button" class="btn btn--ghost" data-user-admin-modal-close>Cancelar</button>
                                <button type="submit" class="btn btn--primary">Guardar cambios</button>
                            </div>
                        </form>
                    </template>
                @endif

                @if($puedeResetClaveStore)
                    <template
                        data-user-admin-template="{{ $usuario->id }}-reset-clave"
                        data-modal-title="Restablecer clave"
                        data-modal-subtitle="{{ $usuario->nombreCompleto() }} · {{ $usuario->email }}"
                    >
                        <form method="POST" action="{{ route('admin.usuarios.reset-clave', $usuario) }}" class="user-admin-modal-form stack" data-loading-label="Generando clave…">
                            @csrf
                            <p class="logout-modal__text" style="margin: 0;">
                                Se generará una contraseña temporal y se enviará al correo
                                <strong>{{ $usuario->email }}</strong>. El usuario deberá cambiarla al iniciar sesión.
                            </p>
                            <div class="logout-modal__actions">
                                <button type="button" class="btn btn--ghost" data-user-admin-modal-close>Cancelar</button>
                                <button type="submit" class="btn btn--primary">Confirmar restablecimiento</button>
                            </div>
                        </form>
                    </template>
                @endif

                @if($puedeDelegarStore)
                    @php
                        $propiasDelRolStore = array_map(
                            static fn (\App\Enums\CapabilityType $capability): string => $capability->value,
                            $usuario->rol->defaultCapabilities(),
                        );
                        $asignadasStore = $usuario->capabilities
                            ->map(static fn ($cap): string => $cap->capability->value)
                            ->all();
                        $otorgablesStore = array_values(array_filter(
                            $capabilities,
                            static fn (\App\Enums\CapabilityType $capability): bool => ! in_array($capability->value, $propiasDelRolStore, true),
                        ));
                    @endphp
                    <template
                        data-user-admin-template="{{ $usuario->id }}-delegar"
                        data-modal-title="Delegar capacidad"
                        data-modal-subtitle="{{ $usuario->nombreCompleto() }} · {{ $usuario->email }}"
                    >
                        <form method="POST" action="{{ route('admin.usuarios.delegate', $usuario) }}" class="user-admin-modal-form stack" data-loading-label="Guardando funciones…">
                            @csrf
                            <p class="logout-modal__text" style="margin: 0;">
                                Active las funciones que esta persona puede ejercer.
                                Firmar solicitudes o enviar faltas de Talento Humano queda a nombre del Decano.
                            </p>
                            @if($otorgablesStore === [])
                                <p class="field-hint" style="margin: 0;">Este rol ya incluye las funciones que puede delegar.</p>
                            @else
                                <fieldset class="capability-picker">
                                    <legend class="field__label">Funciones delegables</legend>
                                    @foreach($otorgablesStore as $capability)
                                        <label class="capability-picker__option">
                                            <input
                                                type="checkbox"
                                                name="capabilities[]"
                                                value="{{ $capability->value }}"
                                                @checked(in_array($capability->value, $asignadasStore, true))
                                            >
                                            <span>
                                                <strong>{{ $capability->label() }}</strong>
                                                @if($capability === \App\Enums\CapabilityType::AprobarSolicitudes)
                                                    <span class="field-hint">Visto bueno o firma de las solicitudes. El trámite queda firmado por el Decano.</span>
                                                @elseif($capability === \App\Enums\CapabilityType::EmitirNotificacionesFaltas)
                                                    <span class="field-hint">Envío de faltas de Talento Humano. El oficio sale del usuario del Decano.</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </fieldset>
                            @endif
                            @if($propiasDelRolStore !== [])
                                <p class="field-hint" style="margin: 0;">
                                    Ya incluidas en el rol:
                                    {{ collect($usuario->rol->defaultCapabilities())->map(static fn (\App\Enums\CapabilityType $capability): string => $capability->label())->join(', ') }}.
                                </p>
                            @endif
                            <div class="logout-modal__actions">
                                <button type="button" class="btn btn--ghost" data-user-admin-modal-close>Cancelar</button>
                                <button type="submit" class="btn btn--primary" @disabled($otorgablesStore === [])>Guardar funciones</button>
                            </div>
                        </form>
                    </template>
                @endif
        @endforeach
    </div>

    <div class="logout-modal" hidden data-user-admin-modal role="dialog" aria-modal="true" aria-labelledby="user-admin-modal-title">
        <button type="button" class="logout-modal__backdrop" aria-label="Cerrar" data-user-admin-modal-close></button>
        <div class="logout-modal__panel user-admin-modal__panel">
            <h2 id="user-admin-modal-title" class="logout-modal__title" data-user-admin-modal-title></h2>
            <p class="user-admin-modal__subtitle" data-user-admin-modal-subtitle hidden></p>
            <div data-user-admin-modal-body></div>
        </div>
    </div>
</section>

<style>
    .data-table--users { table-layout: auto; }
    .data-table__cell--capabilities { min-width: 11rem; }
    .data-table__cell--actions { min-width: 11.5rem; }
    .capability-list {
        list-style: none;
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }
    .capability-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
        margin: 0;
        padding: 0.3rem 0.55rem;
        font-size: 0.72rem;
        font-weight: 600;
        line-height: 1.35;
        color: #334155;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .capability-list__tag {
        flex: none;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: #1e4f8a;
    }
    .capability-picker {
        margin: 0;
        padding: 0;
        border: 0;
        display: flex;
        flex-direction: column;
        gap: 0.55rem;
    }
    .capability-picker__option {
        display: flex;
        gap: 0.65rem;
        align-items: flex-start;
        margin: 0;
        padding: 0.65rem 0.75rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #f8fafc;
    }
    .capability-picker__option input {
        margin-top: 0.2rem;
    }
    .capability-picker__option strong,
    .capability-picker__option .field-hint {
        display: block;
    }
    .user-action-picker {
        width: 100%;
        min-width: 10.5rem;
        max-width: 14rem;
        box-sizing: border-box;
    }
    .user-admin-modal__panel .btn--ghost {
        background: #e8ecf2;
        color: #1a2332;
        border: 1px solid #cdd5e1;
    }
</style>

<script>
window.__SAVA_USER_ADMIN_MODALS = true;
(function () {
    function initUserAdminModalsInline() {
        var modal = document.querySelector('[data-user-admin-modal]');
        if (!modal) return;

        var titleEl = modal.querySelector('[data-user-admin-modal-title]');
        var subtitleEl = modal.querySelector('[data-user-admin-modal-subtitle]');
        var bodyEl = modal.querySelector('[data-user-admin-modal-body]');

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            if (bodyEl) bodyEl.innerHTML = '';
        }

        function openModal(templateEl) {
            if (!titleEl || !bodyEl || !templateEl || !templateEl.content) return;

            titleEl.textContent = templateEl.getAttribute('data-modal-title') || 'Acción';
            var subtitle = templateEl.getAttribute('data-modal-subtitle') || '';
            if (subtitleEl) {
                subtitleEl.textContent = subtitle;
                subtitleEl.hidden = !subtitle;
            }

            bodyEl.innerHTML = '';
            bodyEl.appendChild(templateEl.content.cloneNode(true));

            var form = bodyEl.querySelector('form');
            if (form) {
                form.addEventListener('submit', function () {
                    if (typeof showLoading === 'function') {
                        showLoading(form.getAttribute('data-loading-label') || 'Procesando…');
                    }
                }, { once: true });
            }

            modal.hidden = false;
            modal.removeAttribute('hidden');
            modal.setAttribute('aria-hidden', 'false');

            var focusable = bodyEl.querySelector('select, button, input, textarea');
            if (focusable) focusable.focus();
        }

        document.addEventListener('change', function (event) {
            var picker = event.target.closest('[data-user-action-picker]');
            if (!picker) return;

            var action = picker.value;
            if (!action) return;

            var userId = picker.getAttribute('data-user-id');
            var templateEl = document.querySelector(
                'template[data-user-admin-template="' + userId + '-' + action + '"]'
            );

            picker.selectedIndex = 0;

            if (!templateEl) return;
            openModal(templateEl);
        });

        modal.addEventListener('click', function (event) {
            if (event.target.closest('[data-user-admin-modal-close]')) {
                event.preventDefault();
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) closeModal();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUserAdminModalsInline);
    } else {
        initUserAdminModalsInline();
    }
})();
</script>
@endsection
