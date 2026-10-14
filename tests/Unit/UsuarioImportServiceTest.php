<?php

namespace Tests\Unit;

use App\Services\UsuarioImportService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsuarioImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_cedula_invalida(): void
    {
        $csv = implode("\n", [
            'email,nombres,apellidos,cedula,celular,carrera',
            'docente@uleam.edu.ec,Ana,Pérez,1231231231,0991234567,software',
        ]);

        $result = app(UsuarioImportService::class)->importFromCsv($csv);

        $this->assertSame(0, $result['created']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame(0, User::query()->count());
    }

    public function test_rechaza_cedula_duplicada(): void
    {
        User::factory()->create([
            'email' => 'ya@uleam.edu.ec',
            'cedula' => '1712345675',
            'carrera' => 'software',
        ]);

        $csv = implode("\n", [
            'email,nombres,apellidos,cedula,celular,carrera',
            'docente@uleam.edu.ec,Ana,Pérez,1712345675,0991234567,software',
        ]);

        $result = app(UsuarioImportService::class)->importFromCsv($csv);

        $this->assertSame(0, $result['created']);
        $this->assertTrue(collect($result['errors'])->contains(
            fn (string $error): bool => str_contains($error, 'ya está registrada'),
        ));
    }

    public function test_importa_cedula_valida(): void
    {
        $csv = implode("\n", [
            'email,nombres,apellidos,cedula,celular,carrera',
            'docente@uleam.edu.ec,Ana,Pérez,1712345675,0991234567,software',
        ]);

        $result = app(UsuarioImportService::class)->importFromCsv($csv);

        $this->assertSame(1, $result['created']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('1712345675', User::query()->where('email', 'docente@uleam.edu.ec')->value('cedula'));
    }
}
