<?php

use App\Http\Controllers\Admin\AccountRequestController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MicrosoftAuthController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\CertificadoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SolicitarCuentaController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\NotificacionFaltaController;
use App\Http\Controllers\AppNotificationController;
use App\Http\Controllers\SolicitudFirmaDocenteController;
use App\Http\Controllers\SolicitudProcesoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/legal/{documento}', [LegalDocumentController::class, 'show'])
    ->whereIn('documento', ['terminos', 'privacidad'])
    ->name('legal.show');

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect'])->name('auth.microsoft');
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback'])->name('auth.microsoft.callback');

Route::middleware('guest')->group(function (): void {
    Route::get('/recuperar-clave', [PasswordRecoveryController::class, 'create'])->name('recuperar-clave.create');
    Route::post('/recuperar-clave', [PasswordRecoveryController::class, 'store'])
        ->middleware('throttle:3,10')
        ->name('recuperar-clave.store');
    Route::get('/solicitar-cuenta', [SolicitarCuentaController::class, 'create'])->name('solicitar-cuenta.create');
    Route::post('/solicitar-cuenta', [SolicitarCuentaController::class, 'store'])
        ->middleware('throttle:solicitar-cuenta')
        ->name('solicitar-cuenta.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/cambiar-clave', [PasswordChangeController::class, 'edit'])->name('cambiar-clave.edit');
    Route::put('/cambiar-clave', [PasswordChangeController::class, 'update'])->name('cambiar-clave.update');

    Route::get('/perfil/completar', [PerfilController::class, 'completar'])->name('perfil.completar');
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/clave', [PasswordChangeController::class, 'updateFromProfile'])->name('perfil.clave.update');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/logout', [MicrosoftAuthController::class, 'logout'])->name('logout');

    Route::get('/app-notificaciones/{appNotification}/abrir', [AppNotificationController::class, 'open'])
        ->name('app-notificaciones.abrir');
    Route::post('/app-notificaciones/todas-leidas', [AppNotificationController::class, 'markAllRead'])
        ->name('app-notificaciones.todas-leidas');

    Route::get('/solicitudes/nueva/wizard', [SolicitudController::class, 'wizard'])->name('solicitudes.wizard');
    Route::get('/solicitudes/proceso-aprobacion', [SolicitudProcesoController::class, 'index'])
        ->name('solicitudes.proceso');
    Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');

    Route::get('/solicitudes/{solicitud}/preview-oficio', [CertificadoController::class, 'preview'])
        ->name('solicitudes.preview-oficio');
    Route::get('/solicitudes/{solicitud}/oficio/documento', [CertificadoController::class, 'documento'])
        ->name('solicitudes.oficio-documento');
    Route::get('/solicitudes/{solicitud}/oficio/descargar', [CertificadoController::class, 'descargar'])
        ->name('solicitudes.oficio-descargar');
    Route::get('/solicitudes/{solicitud}/adjunto', [SolicitudController::class, 'adjunto'])
        ->name('solicitudes.adjunto');
    Route::get('/solicitudes/{solicitud}/firmar', [SolicitudFirmaDocenteController::class, 'show'])
        ->name('solicitudes.firmar');
    Route::get('/solicitudes/{solicitud}/oficio/pdf-firma', [SolicitudFirmaDocenteController::class, 'pdfFirma'])
        ->name('solicitudes.oficio-pdf-firma');
    Route::post('/solicitudes/{solicitud}/firmar-navegador', [SolicitudFirmaDocenteController::class, 'firmarNavegador'])
        ->name('solicitudes.firmar-navegador');
    Route::post('/solicitudes/{solicitud}/firmar-p12-documento', [SolicitudFirmaDocenteController::class, 'firmarP12Documento'])
        ->name('solicitudes.firmar-p12-documento');
    Route::post('/solicitudes/{solicitud}/firmar-oficio-p12', [SolicitudFirmaDocenteController::class, 'firmarP12Documento'])
        ->name('solicitudes.firmar-oficio-p12');
    Route::post('/solicitudes/{solicitud}/subir-oficio-firmado', [SolicitudFirmaDocenteController::class, 'subirManual'])
        ->name('solicitudes.subir-oficio-firmado');
    Route::post('/solicitudes/{solicitud}/enviar-secretaria', [SolicitudFirmaDocenteController::class, 'enviarSecretaria'])
        ->name('solicitudes.enviar-secretaria');

    Route::resource('solicitudes', SolicitudController::class)
        ->except(['index'])
        ->parameters(['solicitudes' => 'solicitud']);
    Route::post('/solicitudes/{solicitud}/revisar', [SolicitudProcesoController::class, 'revisar'])
        ->name('solicitudes.revisar');
    Route::post('/solicitudes/{solicitud}/aprobar', [SolicitudProcesoController::class, 'aprobar'])
        ->name('solicitudes.aprobar');

    Route::get('/notificaciones-faltas', [NotificacionFaltaController::class, 'index'])->name('notificaciones-faltas.index');
    Route::post('/notificaciones-faltas', [NotificacionFaltaController::class, 'store'])->name('notificaciones-faltas.store');
    Route::post('/notificaciones-faltas/importar', [NotificacionFaltaController::class, 'import'])->name('notificaciones-faltas.import');
    Route::get('/notificaciones-faltas/{notificacionFalta}', [NotificacionFaltaController::class, 'show'])->name('notificaciones-faltas.show');
    Route::post('/notificaciones-faltas/{notificacionFalta}/reenviar-correo', [NotificacionFaltaController::class, 'reenviarCorreo'])
        ->name('notificaciones-faltas.reenviar-correo');
    Route::get('/notificaciones-faltas/{notificacionFalta}/preview', [NotificacionFaltaController::class, 'preview'])->name('notificaciones-faltas.preview');
    Route::get('/notificaciones-faltas/{notificacionFalta}/documento', [NotificacionFaltaController::class, 'documento'])->name('notificaciones-faltas.documento');
    Route::get('/notificaciones-faltas/{notificacionFalta}/descargar', [NotificacionFaltaController::class, 'descargar'])->name('notificaciones-faltas.descargar');

    Route::get('/secretaria/reportes', [ReporteController::class, 'index'])->name('reportes.index');
    Route::get('/secretaria/reportes/export', [ReporteController::class, 'export'])->name('reportes.export');

    Route::get('/admin/usuarios', [UsuarioController::class, 'index'])->name('admin.usuarios.index');
    Route::post('/admin/usuarios', [UsuarioController::class, 'store'])->name('admin.usuarios.store');
    Route::patch('/admin/usuarios/{usuario}/rol', [UsuarioController::class, 'updateRol'])->name('admin.usuarios.rol');
    Route::patch('/admin/usuarios/{usuario}/estado', [UsuarioController::class, 'updateEstado'])->name('admin.usuarios.estado');
    Route::post('/admin/usuarios/{usuario}/reset-clave', [UsuarioController::class, 'resetClave'])->name('admin.usuarios.reset-clave');
    Route::post('/admin/usuarios/importar-csv', [UsuarioController::class, 'importCsv'])->name('admin.usuarios.import-csv');
    Route::post('/admin/usuarios/{usuario}/delegar', [UsuarioController::class, 'delegate'])
        ->name('admin.usuarios.delegate');

    Route::get('/admin/solicitudes-cuenta', [AccountRequestController::class, 'index'])
        ->name('admin.solicitudes-cuenta.index');
    Route::post('/admin/solicitudes-cuenta/{accountRequest}/aprobar', [AccountRequestController::class, 'aprobar'])
        ->name('admin.solicitudes-cuenta.aprobar');
    Route::post('/admin/solicitudes-cuenta/{accountRequest}/rechazar', [AccountRequestController::class, 'rechazar'])
        ->name('admin.solicitudes-cuenta.rechazar');
});
