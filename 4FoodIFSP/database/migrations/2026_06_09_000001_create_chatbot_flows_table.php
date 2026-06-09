<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wa_connection_id')->nullable()
                ->constrained('wa_connections')->nullOnDelete();
            $table->string('name');
            $table->boolean('active')->default(false);   // só um fluxo ativo por conexão
            $table->string('trigger_keyword')->nullable(); // null = dispara em qualquer 1ª mensagem
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_flows');
    }
};
