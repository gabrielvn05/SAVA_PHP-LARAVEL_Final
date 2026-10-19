<?php

namespace Tests\Feature;

use App\Enums\AppRole;
use App\Models\BibliotecaDocumento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BibliotecaTest extends TestCase
{
    use RefreshDatabase;

    public function test_todas_las_cuentas_consultan_los_oficios_en_solo_lectura(): void
    {
        $docente = $this->usuario(AppRole::Docente, '1712345675');

        $this->actingAs($docente)
            ->get(route('biblioteca.index'))
            ->assertOk()
            ->assertSee('Biblioteca', false)
            ->assertSee('Memorando Circular No. Uleam-DATH-2026-025-MC', false)
            ->assertSee('MEMORÁNDUM No. Uleam-FCVT-2026-027-M', false)
            ->assertSee('MOLINA CHAVEZ MARIA ESPERANZA', false)
            ->assertSee('Descargar', false)
            ->assertDontSee('Subir a la biblioteca', false)
            ->assertDontSee('>Quitar<', false);

        $documento = BibliotecaDocumento::query()->where('titulo', 'like', 'Memorando Circular%')->first();
        $this->assertNotNull($documento);

        $this->actingAs($docente)
            ->get(route('biblioteca.descargar', $documento))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($docente)
            ->post(route('biblioteca.store'), [
                'archivo' => UploadedFile::fake()->create('oficio.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($docente)
            ->delete(route('biblioteca.destroy', $documento))
            ->assertForbidden();

        $this->assertNotNull($documento->fresh());
    }

    public function test_secretaria_del_decano_puede_subir_y_quitar_archivos(): void
    {
        Storage::fake('local');
        $secretaria = $this->usuario(AppRole::Secretaria, '0926687856');

        $this->actingAs($secretaria)
            ->get(route('biblioteca.index'))
            ->assertOk()
            ->assertSee('Subir a la biblioteca', false)
            ->assertSee('Quitar', false);

        $this->actingAs($secretaria)
            ->post(route('biblioteca.store'), [
                'titulo' => 'Oficio de prueba',
                'archivo' => UploadedFile::fake()->create('oficio-nuevo.pdf', 30, 'application/pdf'),
            ])
            ->assertRedirect(route('biblioteca.index'))
            ->assertSessionHas('success');

        $nuevo = BibliotecaDocumento::query()->where('titulo', 'Oficio de prueba')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('SECRETARIA ACADEMICA', $nuevo->autor_nombre);
        Storage::disk('local')->assertExists($nuevo->ruta);

        $this->actingAs($secretaria)
            ->get(route('biblioteca.descargar', $nuevo))
            ->assertOk();

        $this->actingAs($secretaria)
            ->delete(route('biblioteca.destroy', $nuevo))
            ->assertRedirect(route('biblioteca.index'))
            ->assertSessionHas('success');

        $this->assertNull($nuevo->fresh());
        Storage::disk('local')->assertMissing($nuevo->ruta);
    }

    public function test_decano_y_superusuario_no_editan_la_biblioteca(): void
    {
        $decano = $this->usuario(AppRole::Decano, '1313000001');
        $super = $this->usuario(AppRole::Superusuario, '1313000002');
        $documento = BibliotecaDocumento::query()->first();
        $this->assertNotNull($documento);

        foreach ([$decano, $super] as $usuario) {
            $this->actingAs($usuario)
                ->get(route('biblioteca.index'))
                ->assertOk()
                ->assertDontSee('Subir a la biblioteca', false);

            $this->actingAs($usuario)
                ->delete(route('biblioteca.destroy', $documento))
                ->assertForbidden();
        }
    }

    private function usuario(AppRole $rol, string $cedula): User
    {
        return User::factory()->create([
            'rol' => $rol,
            'cedula' => $cedula,
            'carrera' => 'software',
            'celular' => '0991234567',
            'nombres' => 'Secretaria',
            'apellidos' => 'Academica',
        ]);
    }
}
