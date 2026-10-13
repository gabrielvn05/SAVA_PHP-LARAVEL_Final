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
<body class="app-public">
    <a class="skip-to-main" href="#contenido-principal">Saltar al contenido</a>
    @yield('content')
</body>
</html>
