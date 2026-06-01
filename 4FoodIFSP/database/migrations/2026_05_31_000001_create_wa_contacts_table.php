<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            // Identificador WhatsApp (JID/LID) capturado na inserção automática
            // ou número composto (55 + DDD + número) nas inserções manuais.
            $table->string('phone_number');
            // Dígitos nacionais (DDD + número, sem o código do país) usados como
            // chave de deduplicação e para a busca por número.
            $table->string('phone_digits')->unique();
            $table->string('ddd', 3)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('cpf', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_contacts');
    }
};
