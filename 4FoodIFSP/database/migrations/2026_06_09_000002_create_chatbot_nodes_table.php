<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_nodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chatbot_flow_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['start', 'message', 'action']);
            $table->json('payload')->nullable();   // { text } | { action: 'handoff'|'reservation'|'end' }
            $table->integer('position_x')->default(0);
            $table->integer('position_y')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_nodes');
    }
};
