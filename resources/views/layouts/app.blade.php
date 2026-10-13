<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0c3d7a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="SAVA">
    <title>@yield('title', 'SAVA') — Sistema de Asistencia y Validaciones Académicas</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('branding/icon-192.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('branding/icon-192.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $usuario = auth()->user();
    $rol = $usuario->rol;
    $esSuper = $rol === \App\Enums\AppRole::Superusuario;
    $esDecano = $rol === \App\Enums\AppRole::Decano;
    $esSecretaria = $rol === \App\Enums\AppRole::Secretaria;
    $puedeProceso = $esSecretaria || $esDecano;
    $puedeNotificacionesFaltas = \App\Support\NotificacionFaltaAccess::puedeGestionar($usuario);
    $solicitudesOpen = request()->routeIs('solicitudes.*')
        && ! request()->routeIs('solicitudes.proceso')
        && ! ($puedeProceso && request()->routeIs('solicitudes.show'));
    $inicialesTopbar = collect(preg_split('/\s+/', trim($usuario->nombres.' '.$usuario->apellidos)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
    $identificadorTopbar = trim((string) $usuario->cedula) !== ''
        ? $usuario->cedula
        : $usuario->email;
    $noLeidasApp = (int) ($appNotificacionesNoLeidas ?? 0);
    $etiquetaCampana = $noLeidasApp > 0
        ? "Notificaciones, {$noLeidasApp} sin leer"
        : 'Notificaciones';
@endphp
<body class="app-shell">
    <a class="skip-to-main" href="#contenido-principal">Saltar al contenido</a>

    <div class="app-shell-layout app-shell-layout--with-sidebar" data-app-shell>
        <header class="topbar topbar--light app-shell__header">
            <div class="topbar__start">
                <button
                    type="button"
                    class="sidebar-toggle"
                    data-sidebar-toggle
                    aria-label="Abrir menú"
                    aria-expanded="false"
                >
                    <span class="sidebar-toggle__bar"></span>
                    <span class="sidebar-toggle__bar"></span>
                    <span class="sidebar-toggle__bar"></span>
                </button>
                <div class="topbar__brand">
                    <img class="topbar__logo-img" src="{{ asset('branding/LOGO-ULEAM.png') }}" alt="Universidad Laica Eloy Alfaro de Manabí">
                    <div class="topbar__titles">
                        <span class="topbar__name">SAVA</span>
                        <span class="topbar__tagline">Permisos y justificaciones</span>
                    </div>
                </div>
                <div class="topbar__account">
                    <div class="topbar__avatar" aria-hidden="true">{{ $inicialesTopbar !== '' ? $inicialesTopbar : '?' }}</div>
                    <div class="topbar__account-meta">
                        <span class="topbar__account-name">{{ mb_strtoupper($usuario->nombreCompleto()) }}</span>
                        <span class="topbar__account-id">{{ $identificadorTopbar }}</span>
                    </div>
                </div>
            </div>
            <div class="topbar__actions">
                <div class="topbar-notifications" data-notifications-menu>
                    <button
                        type="button"
                        class="topbar-icon-btn topbar-notifications__toggle"
                        data-notifications-menu-toggle
                        aria-label="{{ $etiquetaCampana }}"
                        aria-haspopup="menu"
                        aria-expanded="false"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M18 16v-5a6 6 0 1 0-12 0v5l-2 2h16l-2-2Z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/>
                            <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                        </svg>
                        @if($noLeidasApp > 0)
                            <span class="topbar-notifications__badge" data-notifications-unread-badge>{{ $noLeidasApp > 99 ? '99+' : $noLeidasApp }}</span>
                        @endif
                    </button>
                    <div class="topbar-notifications__panel" data-notifications-menu-panel role="menu" aria-hidden="true">
                        <div class="topbar-notifications__head">
                            <div class="topbar-notifications__head-text">
                                <span class="topbar-notifications__title">Notificaciones</span>
                                @if($noLeidasApp > 0)
                                    <span class="topbar-notifications__subtitle">{{ $noLeidasApp }} sin leer</span>
                                @endif
                            </div>
                            @if($noLeidasApp > 0)
                                <form method="POST" action="{{ route('app-notificaciones.todas-leidas') }}">
                                    @csrf
                                    <button type="submit" class="topbar-notifications__mark-all">Marcar todas leídas</button>
                                </form>
                            @endif
                        </div>
                        <ul class="topbar-notifications__list">
                            @forelse($appNotificacionesRecientes ?? [] as $notif)
                                <li>
                                    <a
                                        href="{{ route('app-notificaciones.abrir', $notif) }}"
                                        class="topbar-notifications__item {{ $notif->read_at === null ? 'is-unread' : '' }}"
                                        role="menuitem"
                                    >
                                        <span class="topbar-notifications__item-title">{{ $notif->title }}</span>
                                        <span class="topbar-notifications__item-msg">{{ $notif->message }}</span>
                                        <time class="topbar-notifications__item-time" datetime="{{ $notif->created_at->toIso8601String() }}">
                                            {{ $notif->created_at->diffForHumans() }}
                                        </time>
                                    </a>
                                </li>
                            @empty
                                <li class="topbar-notifications__empty">No tiene notificaciones.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
                <div class="topbar-menu" data-topbar-menu>
                    <button
                        type="button"
                        class="topbar-icon-btn topbar-menu__toggle"
                        data-topbar-menu-toggle
                        aria-label="Configuración de cuenta"
                        aria-haspopup="menu"
                        aria-expanded="false"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="1.75"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.26.604-.852.644-1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                    <div class="topbar-menu__panel" data-topbar-menu-panel role="menu" hidden>
                        <a href="{{ route('perfil.edit') }}" class="topbar-menu__item" role="menuitem">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle cx="12" cy="8" r="3.5" stroke="currentColor" stroke-width="1.75"/>
                                <path d="M5 20c0-3.5 3-6 7-6s7 2.5 7 6" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                            </svg>
                            Perfil y configuración
                        </a>
                        <button type="button" class="topbar-menu__item topbar-menu__item--danger" role="menuitem" data-logout-open>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M10 17l5-5-5-5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M15 12H3M14 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                            </svg>
                            Cerrar sesión
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class="app-shell__workspace">
            <div class="sidebar-backdrop" data-sidebar-backdrop aria-hidden="true"></div>
            <aside class="sidebar-panel" data-sidebar-panel aria-label="Menú lateral">
                <nav class="sidebar-nav" aria-label="Menú principal">
                    <a href="{{ route('dashboard') }}" class="sidebar-nav__link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">Inicio</a>

                    @if($esSuper)
                        <a href="{{ route('admin.usuarios.index') }}" class="sidebar-nav__link {{ request()->routeIs('admin.usuarios.*') ? 'is-active' : '' }}">Usuarios</a>
                        <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="sidebar-nav__link {{ request()->routeIs('admin.solicitudes-cuenta.*') ? 'is-active' : '' }}">Solicitudes de cuenta</a>
                    @else
                        <div class="sidebar-nav__group" data-nav-group>
                            <button type="button" class="sidebar-nav__group-btn" aria-expanded="{{ $solicitudesOpen ? 'true' : 'false' }}" data-nav-group-btn>
                                <span>Solicitudes</span>
                                <span class="sidebar-nav__chevron" aria-hidden style="transform: rotate({{ $solicitudesOpen ? '180deg' : '0deg' }});">▼</span>
                            </button>
                            <div class="sidebar-nav__sub" @unless($solicitudesOpen) hidden @endunless data-nav-group-sub>
                                <a href="{{ route('solicitudes.wizard') }}" class="sidebar-nav__sublink {{ request()->routeIs('solicitudes.wizard', 'solicitudes.create') ? 'is-active' : '' }}">Nuevas solicitudes</a>
                                <a href="{{ route('solicitudes.index') }}" class="sidebar-nav__sublink {{ request()->routeIs('solicitudes.index', 'solicitudes.show', 'solicitudes.edit') ? 'is-active' : '' }}">Mis solicitudes</a>
                            </div>
                        </div>

                        @if($puedeProceso)
                            <a href="{{ route('solicitudes.proceso') }}" class="sidebar-nav__link {{ request()->routeIs('solicitudes.proceso') || ($puedeProceso && request()->routeIs('solicitudes.show')) ? 'is-active' : '' }}">Proceso de aprobación</a>
                        @endif
                        @if($usuario->hasCapability(\App\Enums\CapabilityType::GestionarUsuarios))
                            <a href="{{ route('admin.usuarios.index') }}" class="sidebar-nav__link {{ request()->routeIs('admin.usuarios.*') ? 'is-active' : '' }}">Usuarios</a>
                        @endif
                        @if($puedeNotificacionesFaltas)
                            <a href="{{ route('notificaciones-faltas.index') }}" class="sidebar-nav__link {{ request()->routeIs('notificaciones-faltas.*') ? 'is-active' : '' }}">Notificaciones TH</a>
                        @endif
                        @if($esSecretaria || $esDecano)
                            <a href="{{ route('reportes.index') }}" class="sidebar-nav__link {{ request()->routeIs('reportes.*') ? 'is-active' : '' }}">Reportes</a>
                            <a href="{{ route('admin.solicitudes-cuenta.index') }}" class="sidebar-nav__link {{ request()->routeIs('admin.solicitudes-cuenta.*') ? 'is-active' : '' }}">Solicitudes de cuenta</a>
                        @endif
                    @endif
                </nav>
            </aside>

            <div class="app-shell__content page-enter">
                <div class="app-shell__body">
                    <main id="contenido-principal" class="app-main" tabindex="-1">
                        @if(session('success'))
                            <div class="alert alert--success" role="status">{{ session('success') }}</div>
                        @endif
                        @if(session('error'))
                            <div class="alert alert--error" role="alert">{{ session('error') }}</div>
                        @endif
                        @yield('content')
                    </main>
                </div>
            </div>
        </div>
    </div>

    @if(request()->routeIs('perfil.completar'))
        <div class="profile-modal" data-profile-modal role="dialog" aria-modal="true" aria-labelledby="profile-modal-title">
            <div class="profile-modal__backdrop" aria-hidden="true"></div>
            <div class="profile-modal__panel">
                <h2 id="profile-modal-title" class="profile-modal__title">Actualiza tus datos</h2>
                <p class="profile-modal__text">
                    Microsoft 365 no envía cédula ni carrera. Completa estos datos para continuar.
                    Luego podrás cambiarlos en <strong>Perfil y configuración</strong>.
                </p>
                @if($errors->any())
                    <div class="alert alert--error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ route('perfil.update') }}" class="stack" data-loading-label="Guardando datos…">
                    @csrf
                    @method('PUT')
                    @include('perfil._campos', ['user' => $usuario, 'carreras' => \App\Support\Carreras::OPCIONES])
                    <div class="profile-modal__actions">
                        <button type="button" class="btn btn--ghost" data-logout-open>Cerrar sesión</button>
                        <button type="submit" class="btn btn--primary">Guardar y continuar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="logout-modal" hidden data-logout-modal role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
        <button type="button" class="logout-modal__backdrop" aria-label="Cerrar" data-logout-close></button>
        <div class="logout-modal__panel">
            <h2 id="logout-modal-title" class="logout-modal__title">Cerrar sesión</h2>
            <p class="logout-modal__text">¿Seguro que desea cerrar sesión?</p>
            <div class="logout-modal__actions">
                <button type="button" class="btn btn--ghost" data-logout-close>Cancelar</button>
                <button type="submit" class="btn btn--primary" form="logout-form">Aceptar</button>
            </div>
        </div>
    </div>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" hidden>
        @csrf
    </form>
</body>
</html>
