<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_connections', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('connection_status');
        });
    }

    public function down(): void
    {
        Schema::table('wa_connections', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }
};
