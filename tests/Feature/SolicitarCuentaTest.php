<?php

namespace Tests\Feature;

use App\Models\AccountRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolicitarCuentaTest extends TestCase
{
    use RefreshDatabase;

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
            'cedula' => '131559130X',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'cedula_letras'], false));
    }

    public function test_rechaza_cedula_invalida(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload([
            'cedula' => '1231231231',
        ]))
            ->assertRedirect(route('solicitar-cuenta.create', ['aviso' => 'cedula_invalida'], false));
    }

    public function test_acepta_solicitud_valida(): void
    {
        $this->post(route('solicitar-cuenta.store'), $this->payload())
            ->assertRedirect(route('login', ['solicitud' => 'ok'], false));

        $this->assertSame(1, AccountRequest::query()->count());
    }

    /** @param  array<string, string>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombres' => 'Gabriel',
            'apellidos' => 'Velez',
            'cedula' => '1315591303',
            'celular' => '0991234567',
            'email' => 'nuevo.docente@uleam.edu.ec',
            'carrera' => 'software',
            'rol_solicitado' => 'docente',
        ], $overrides);
    }
}
