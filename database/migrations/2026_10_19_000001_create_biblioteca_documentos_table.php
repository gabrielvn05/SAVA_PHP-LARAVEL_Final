<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblioteca_documentos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titulo');
            $table->string('autor_nombre');
            $table->date('fecha_documento');
            $table->string('nombre_archivo');
            $table->string('ruta');
            $table->string('disco', 20);
            $table->string('mime', 120);
            $table->unsignedBigInteger('tamano');
            $table->foreignUuid('subido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $ahora = now();

        foreach ($this->documentosIniciales() as $documento) {
            $path = resource_path('biblioteca/'.$documento['ruta']);
            if (! is_readable($path)) {
                continue;
            }

            DB::table('biblioteca_documentos')->insert([
                'id' => (string) Str::uuid(),
                'titulo' => $documento['titulo'],
                'autor_nombre' => 'MOLINA CHAVEZ MARIA ESPERANZA',
                'fecha_documento' => '2026-10-02',
                'nombre_archivo' => $documento['nombre_archivo'],
                'ruta' => $documento['ruta'],
                'disco' => 'recurso',
                'mime' => 'application/pdf',
                'tamano' => filesize($path) ?: 0,
                'subido_por' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('biblioteca_documentos');
    }

    /** @return list<array{titulo: string, nombre_archivo: string, ruta: string}> */
    private function documentosIniciales(): array
    {
        return [
            [
                'titulo' => 'Memorando Circular No. Uleam-DATH-2026-025-MC',
                'nombre_archivo' => 'Memorando Circular No. Uleam-DATH-2026-025-MC.pdf',
                'ruta' => 'memorando-circular-uleam-dath-2026-025-mc.pdf',
            ],
            [
                'titulo' => 'MEMORÁNDUM No. Uleam-FCVT-2026-027-M',
                'nombre_archivo' => 'MEMORÁNDUM No. Uleam-FCVT-2026-027-M.pdf',
                'ruta' => 'memorandum-uleam-fcvt-2026-027-m.pdf',
            ],
        ];
    }
};
