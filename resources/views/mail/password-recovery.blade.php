<div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.5;color:#1a2332">
    <h2 style="margin:0 0 12px">Recuperación de contraseña</h2>
    <p>Hola <strong>{{ $fullName }}</strong>,</p>
    <p>Recibimos una solicitud para restablecer el acceso a tu cuenta en SAVA.</p>
    <p>
        <strong>Usuario:</strong> {{ $email }}<br>
        <strong>Clave temporal:</strong> <code style="font-size:15px">{{ $temporaryPassword }}</code>
    </p>
    <p>Ingresa en <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>. Por seguridad, deberás definir una nueva contraseña al iniciar sesión.</p>
    <p style="color:#5c6b7f">Si no solicitaste este cambio, contacta al administrador de SAVA de inmediato.</p>
</div>
