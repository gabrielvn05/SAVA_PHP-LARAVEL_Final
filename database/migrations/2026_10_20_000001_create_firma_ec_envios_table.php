<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firma_ec_envios', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cedula', 20);
            $table->string('nombre_documento')->unique();
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedSmallInteger('pagina')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('firmado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firma_ec_envios');
    }
};
