<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_edges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chatbot_flow_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('from_node_id')->constrained('chatbot_nodes')->cascadeOnDelete();
            $table->foreignUuid('to_node_id')->constrained('chatbot_nodes')->cascadeOnDelete();
            $table->string('match_value')->nullable(); // "1".."9" | null (auto)
            $table->string('label')->nullable();       // "Ver cardápio" (exibido no canvas)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_edges');
    }
};
