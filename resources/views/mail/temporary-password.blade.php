<div style="font-family:Segoe UI,Arial,sans-serif;line-height:1.5;color:#1a2332">
    <h2 style="margin:0 0 12px">Tu cuenta fue aprobada</h2>
    <p>Hola <strong>{{ $fullName }}</strong>,</p>
    <p>El Decanato aprobó tu solicitud de acceso en SAVA.</p>
    <p>
        <strong>Usuario:</strong> {{ $email }}<br>
        <strong>Clave temporal:</strong> <code style="font-size:15px">{{ $temporaryPassword }}</code>
    </p>
    <p>Ingresa en <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>. En el primer inicio, el sistema te pedirá cambiar la contraseña.</p>
    <p style="color:#5c6b7f">Si no solicitaste esta cuenta, ignora este mensaje.</p>
</div>
