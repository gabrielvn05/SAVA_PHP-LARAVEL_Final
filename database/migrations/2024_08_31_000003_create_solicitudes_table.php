<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('creado_por')->constrained('users');
            $table->string('tipo')->default('justificacion');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->text('motivo');
            $table->jsonb('detalle')->default('{}');
            $table->string('justificativo_path')->nullable();
            $table->string('justificativo_nombre')->nullable();
            $table->string('estado')->default('en_revision_secretaria');
            $table->foreignUuid('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('firmado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones_secretaria')->nullable();
            $table->text('observaciones_decano')->nullable();
            $table->timestamp('fecha_firma')->nullable();
            $table->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE solicitudes ADD CONSTRAINT solicitudes_fecha_check CHECK (fecha_fin >= fecha_inicio)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
