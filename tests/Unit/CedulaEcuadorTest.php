<?php

namespace Tests\Unit;

use App\Support\CedulaEcuador;
use Tests\TestCase;

class CedulaEcuadorTest extends TestCase
{
    public function test_rechaza_letras_en_entrada(): void
    {
        $this->assertTrue(CedulaEcuador::containsLetters('1315591303a'));
        $this->assertFalse(CedulaEcuador::containsLetters('1315591303'));
    }

    public function test_normaliza_y_limita_a_diez_digitos(): void
    {
        $this->assertSame('1315591303', CedulaEcuador::normalizeDigits('131-559-1303-99'));
    }

    public function test_rechaza_cedulas_triviales(): void
    {
        $this->assertFalse(CedulaEcuador::isValid('1231231231'));
        $this->assertFalse(CedulaEcuador::isValid('1111111111'));
    }

    public function test_acepta_cedula_ecuatoriana_valida(): void
    {
        $this->assertTrue(CedulaEcuador::isValid('1315591303'));
    }
}
