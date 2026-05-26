<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('table_id')->constrained('tables')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('closed_at')->nullable();
            $table->enum('closed_reason', ['paid', 'admin_release', 'timeout'])->nullable();
            $table->timestamps();

            $table->index(['table_id', 'closed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
