<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->foreignUuid('wa_connection_id')
                ->nullable()
                ->after('id')
                ->constrained('wa_connections')
                ->nullOnDelete();

            $table->index(['phone_number', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->dropForeign(['wa_connection_id']);
            $table->dropIndex(['phone_number', 'status']);
            $table->dropColumn('wa_connection_id');
        });
    }
};
