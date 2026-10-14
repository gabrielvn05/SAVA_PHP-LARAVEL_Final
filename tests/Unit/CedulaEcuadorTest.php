<?php

namespace Tests\Unit;

use App\Support\CedulaEcuador;
use Tests\TestCase;

class CedulaEcuadorTest extends TestCase
{
    public function test_rechaza_letras_en_entrada(): void
    {
        $this->assertTrue(CedulaEcuador::containsLetters('1712345675a'));
        $this->assertFalse(CedulaEcuador::containsLetters('1712345675'));
    }

    public function test_normaliza_y_limita_a_diez_digitos(): void
    {
        $this->assertSame('1712345675', CedulaEcuador::normalizeDigits('171-234-5675-99'));
    }

    public function test_rechaza_cedulas_triviales(): void
    {
        $this->assertFalse(CedulaEcuador::isValid('1231231231'));
        $this->assertFalse(CedulaEcuador::isValid('1111111111'));
    }

    public function test_acepta_cedula_ecuatoriana_valida(): void
    {
        $this->assertTrue(CedulaEcuador::isValid('1712345675'));
    }

    public function test_genera_cedulas_sinteticas_validas(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->assertTrue(CedulaEcuador::isValid(CedulaEcuador::fake()));
        }
    }
}
