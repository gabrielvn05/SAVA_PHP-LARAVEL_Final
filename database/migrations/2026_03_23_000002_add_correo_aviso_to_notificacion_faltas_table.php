<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificacion_faltas', function (Blueprint $table) {
            $table->timestamp('aviso_correo_enviado_at')->nullable()->after('notas');
            $table->string('aviso_correo_error', 500)->nullable()->after('aviso_correo_enviado_at');
        });
    }

    public function down(): void
    {
        Schema::table('notificacion_faltas', function (Blueprint $table) {
            $table->dropColumn(['aviso_correo_enviado_at', 'aviso_correo_error']);
        });
    }
};
