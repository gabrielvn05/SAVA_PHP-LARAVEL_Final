<?php

namespace Tests\Feature;

use App\Enums\AccountRequestStatus;
use App\Enums\AppRole;
use App\Models\AccountRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SolicitarCuentaTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_declaraciones_obligatorias_y_los_documentos(): void
    {
        $this->get(route('solicitar-cuenta.create'))
            ->assertOk()
            ->assertSee('Declaro que la información proporcionada', false)
            ->assertSee('Acepto los', false)
            ->assertSee(route('legal.show', 'terminos'), false)
            ->assertSee(route('legal.show', 'privacidad'), false);

        $this->get(route('legal.show', 'terminos'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->get(route('legal.show', 'privacidad'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_rechaza_solicitud_sin_declaraciones(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'declaracion_veraz' => '0',
            'acepto_terminos' => null,
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'declaraciones_pendientes'], false));

        $this->assertSame(0, AccountRequest::query()->count());
    }

    public function test_rechaza_correo_temporal(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'email' => 'prueba@mailinator.com',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'correo_no_institucional'], false));

        $this->assertSame(0, AccountRequest::query()->count());
    }

    public function test_rechaza_cedula_con_letras(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'cedula' => '171234567X',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'cedula_letras'], false));
    }

    public function test_acepta_cedula_con_diez_digitos_sin_validar_digito_verificador(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'cedula' => '1231231231',
        ]))
            ->assertRedirect(route('login', ['solicitud' => 'ok'], false));

        $this->assertSame(1, AccountRequest::query()->count());
        $this->assertSame('1231231231', AccountRequest::query()->value('cedula'));
    }

    public function test_rechaza_cedula_con_menos_de_diez_digitos(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'cedula' => '12345',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'cedula_formato'], false));
    }

    public function test_acepta_solicitud_valida(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload())
            ->assertRedirect(route('login', ['solicitud' => 'ok'], false));

        $this->assertSame(1, AccountRequest::query()->count());
        $this->assertSame('GABRIEL', AccountRequest::query()->value('nombres'));
        $this->assertSame('VELEZ', AccountRequest::query()->value('apellidos'));
    }

    public function test_guarda_nombres_y_apellidos_en_mayusculas_sin_tildes_perdidas(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'nombres' => 'maría elena',
            'apellidos' => 'núñez pérez',
            'email' => 'maria.elena@uleam.edu.ec',
            'cedula' => '0926687856',
        ]))
            ->assertRedirect(route('login', ['solicitud' => 'ok'], false));

        $solicitud = AccountRequest::query()->first();
        $this->assertNotNull($solicitud);
        $this->assertSame('MARÍA ELENA', $solicitud->nombres);
        $this->assertSame('NÚÑEZ PÉREZ', $solicitud->apellidos);
    }

    public function test_rechaza_numeros_o_caracteres_especiales_en_nombres_y_apellidos(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'nombres' => 'GABRIEL2',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'nombres_invalidos'], false));

        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'apellidos' => 'VELEZ-NUÑEZ',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'apellidos_invalidos'], false));

        $this->assertSame(0, AccountRequest::query()->count());
    }

    public function test_formulario_indica_mayusculas_y_solo_letras_en_nombres(): void
    {
        $this->get(route('solicitar-cuenta.create'))
            ->assertOk()
            ->assertSee('data-solo-letras', false)
            ->assertSee('Ingrese los dos nombres en mayúsculas', false)
            ->assertSee('Ingrese los dos apellidos en mayúsculas', false);
    }

    public function test_rechaza_rol_privilegiado_en_formulario_publico(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'rol_solicitado' => 'decano',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'rol_invalido'], false));

        $this->assertSame(0, AccountRequest::query()->count());
    }

    public function test_rechaza_cedula_ya_registrada(): void
    {
        User::factory()->create([
            'email' => 'existente@uleam.edu.ec',
            'cedula' => '1712345675',
            'carrera' => 'software',
        ]);

        $this->post(route('solicitar-cuenta.store'), $this->payload())
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'cedula_registrada'], false));
    }

    public function test_permite_nueva_solicitud_despues_de_un_rechazo(): void
    {
        $decano = User::factory()->create([
            'rol' => AppRole::Decano,
            'cedula' => '0951234566',
            'carrera' => 'software',
        ]);

        $this->post(route('solicitar-cuenta.store'), $this->payload())
            ->assertRedirect(route('login', ['solicitud' => 'ok'], false));

        $primera = AccountRequest::query()->first();
        $this->assertNotNull($primera);

        $this->actingAs($decano)
            ->post(route('admin.solicitudes-cuenta.rechazar', $primera), [
                'rechazo_comentario' => 'Documentación incompleta',
            ])
            ->assertRedirect();

        Auth::logout();
        $this->flushSession();

        $this->post(route('solicitar-cuenta.store'), $this->payload())
            ->assertRedirect(route('login', ['solicitud' => 'ok'], false));

        $segunda = AccountRequest::query()
            ->where('status', AccountRequestStatus::Pendiente)
            ->first();
        $this->assertNotNull($segunda);

        $this->actingAs($decano)
            ->post(route('admin.solicitudes-cuenta.rechazar', $segunda), [
                'rechazo_comentario' => 'Aún no corresponde',
            ])
            ->assertRedirect();

        $this->assertSame(2, AccountRequest::query()->where('status', AccountRequestStatus::Rechazada)->count());
    }

    /** @param  array<string, string>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombres' => 'Gabriel',
            'apellidos' => 'Velez',
            'cedula' => '1712345675',
            'celular' => '0991234567',
            'email' => 'nuevo.docente@uleam.edu.ec',
            'carrera' => 'software',
            'rol_solicitado' => 'docente',
            'declaracion_veraz' => '1',
            'declaracion_terminos' => '1',
            'declaracion_privacidad' => '1',
            'acepto_terminos' => '1',
        ], $overrides);
    }
}
