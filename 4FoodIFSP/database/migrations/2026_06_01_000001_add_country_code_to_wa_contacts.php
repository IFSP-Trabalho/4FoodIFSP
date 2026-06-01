<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->string('country_code', 4)->default('55')->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->dropColumn('country_code');
        });
    }
};
