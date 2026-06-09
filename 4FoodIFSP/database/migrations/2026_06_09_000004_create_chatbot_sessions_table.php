<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chatbot_flow_id')->constrained()->cascadeOnDelete();
            $table->string('phone_number');                 // E.164 / JID do cliente
            $table->foreignUuid('current_node_id')->nullable()
                ->constrained('chatbot_nodes')->nullOnDelete();
            $table->json('context')->nullable();            // variáveis coletadas (nº pessoas, etc.)
            $table->enum('status', ['active', 'handoff', 'completed'])->default('active');
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->index(['phone_number', 'status']); // buscar a sessão ativa de um número
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_sessions');
    }
};
