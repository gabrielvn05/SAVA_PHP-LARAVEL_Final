# SAVA — Laravel + PostgreSQL + Office 365

Sistema de Asistencia y Validaciones Académicas migrado a **Laravel 12**, **PHP 8.3** y **PostgreSQL**, con autenticación exclusiva mediante **Microsoft Office 365** (Azure AD).

## Requisitos

- PHP 8.2+
- Composer
- PostgreSQL 16
- Node.js 16+ (para compilar assets con Vite)
- App registrada en Azure AD (Entra ID)

## Instalación local

```bash
cd C:\SAVA_PHP+LARAVEL
cp .env.example .env
composer install
php artisan key:generate
npm install && npm run build
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

La aplicación quedará en `http://localhost:8000`.

## Docker

```bash
docker compose up -d postgres
# Esperar a que PostgreSQL esté listo, luego:
composer install
php artisan migrate --seed
docker compose up app
```

## Azure AD / Office 365

1. Registra una aplicación en [Azure Portal](https://portal.azure.com) → Microsoft Entra ID → App registrations.
2. Configura el redirect URI: `http://localhost:8000/auth/microsoft/callback`
3. Crea un client secret.
4. Copia en `.env`:

```env
AZURE_CLIENT_ID=tu-client-id
AZURE_CLIENT_SECRET=tu-client-secret
AZURE_TENANT_ID=tu-tenant-id
AZURE_REDIRECT_URI="${APP_URL}/auth/microsoft/callback"
```

## Usuarios de prueba (seeder)

| Correo | Rol |
|--------|-----|
| decano@uleam.edu.ec | Decano |
| secretaria@uleam.edu.ec | Secretaría |
| docente@uleam.edu.ec | Docente |

Estos usuarios deben existir también en Azure AD (o usar correos reales de tu tenant) para poder iniciar sesión.

## Estructura principal

```
app/
  Enums/          Roles, estados, tipos de solicitud
  Models/         User, Solicitud, AccountRequest, AuditLog
  Policies/       Autorización por rol/capability
  Services/       Workflow, auditoría, estadísticas
  Http/Controllers/
routes/web.php    Rutas web
database/migrations/  Esquema PostgreSQL
resources/views/  Vistas Blade
```

## Flujo de autenticación

1. El usuario hace clic en **Iniciar sesión con Microsoft 365**.
2. Laravel Socialite redirige a Azure AD.
3. Tras autenticarse, se busca el usuario por correo en la tabla `users`.
4. Si no existe o está inactivo, se rechaza el acceso.
5. Si existe, se actualiza `microsoft_id` y se crea la sesión.

## Solicitudes de cuenta

Los usuarios sin cuenta pueden solicitar acceso en `/solicitar-cuenta`. El Decano aprueba desde `/admin/solicitudes-cuenta`, lo que crea el registro en `users` (sin contraseña; el login es solo vía Office 365).

## Módulos implementados

- Dashboard con estadísticas
- CRUD de solicitudes (permisos, justificaciones, viajes, etc.)
- Proceso de revisión (Secretaría) y aprobación (Decano)
- Gestión de usuarios
- Solicitudes de cuenta
- Auditoría (`audit_log`)

## Pendiente / fuera de alcance inicial

- Generación de certificados DOCX/PDF
- Reportes consolidados
- Firma digital avanzada

Estos módulos existen en el proyecto Next.js original y pueden migrarse en fases posteriores.
