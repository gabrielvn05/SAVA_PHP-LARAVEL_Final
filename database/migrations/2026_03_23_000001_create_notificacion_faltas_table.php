<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacion_faltas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('destinatario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('decano_id')->constrained('users');
            $table->foreignUuid('emitido_por_id')->constrained('users');
            $table->date('fecha_falta');
            $table->string('tipo_marcacion', 30);
            $table->string('codigo_oficio', 48)->unique();
            $table->boolean('emitido_delegado')->default(false);
            $table->boolean('tiene_justificacion_aprobada')->default(false);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['destinatario_id', 'fecha_falta', 'tipo_marcacion'], 'notificacion_faltas_unica');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacion_faltas');
    }
};
