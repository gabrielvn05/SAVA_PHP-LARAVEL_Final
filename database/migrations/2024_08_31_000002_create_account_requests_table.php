<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('cedula')->default('');
            $table->string('celular')->default('');
            $table->string('carrera')->default('');
            $table->string('jornada')->default('');
            $table->string('rol_solicitado')->default('administrativo');
            $table->text('motivo')->nullable();
            $table->string('status')->default('pendiente');
            $table->text('rechazo_comentario')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->unique(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_requests');
    }
};
