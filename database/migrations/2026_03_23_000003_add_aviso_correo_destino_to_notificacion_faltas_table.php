<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacion_faltas', function (Blueprint $table) {
            $table->string('aviso_correo_destino', 255)->nullable()->after('aviso_correo_enviado_at');
        });
    }

    public function down(): void
    {
        Schema::table('notificacion_faltas', function (Blueprint $table) {
            $table->dropColumn('aviso_correo_destino');
        });
    }
};
