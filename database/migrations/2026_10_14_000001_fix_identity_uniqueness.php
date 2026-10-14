<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_requests', function (Blueprint $table) {
            $table->dropUnique(['email', 'status']);
        });

        DB::statement("CREATE UNIQUE INDEX account_requests_email_pendiente_unique ON account_requests (email) WHERE status = 'pendiente'");

        $cedulasDuplicadas = DB::table('users')
            ->select('cedula')
            ->where('cedula', '!=', '')
            ->groupBy('cedula')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $cedulasDuplicadas) {
            DB::statement("CREATE UNIQUE INDEX users_cedula_not_empty_unique ON users (cedula) WHERE cedula <> ''");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS account_requests_email_pendiente_unique');
        DB::statement('DROP INDEX IF EXISTS users_cedula_not_empty_unique');

        Schema::table('account_requests', function (Blueprint $table) {
            $table->unique(['email', 'status']);
        });
    }
};
