<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('type')->default('text')->after('direction');
            $table->decimal('latitude', 10, 7)->nullable()->after('body');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn(['type', 'latitude', 'longitude']);
        });
    }
};
