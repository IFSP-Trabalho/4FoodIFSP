<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('channel_type')->default('whatsapp');
            $table->string('phone_number')->nullable();
            $table->string('connection_status')->default('disconnected');
            $table->timestamp('last_status_at')->nullable();
            $table->string('baileys_session_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_connections');
    }
};
