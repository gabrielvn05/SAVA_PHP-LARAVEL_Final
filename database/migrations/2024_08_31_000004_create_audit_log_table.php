<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('table_name');
            $table->string('record_id');
            $table->string('action');
            $table->foreignUuid('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->jsonb('old_data')->nullable();
            $table->jsonb('new_data')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['table_name', 'record_id']);
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
