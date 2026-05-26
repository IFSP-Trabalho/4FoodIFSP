<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->enum('payment_method', ['dinheiro', 'pix', 'debito', 'credito', 'misto'])
                  ->nullable()
                  ->after('closed_reason');

            $table->decimal('discount_amount', 8, 2)
                  ->default(0)
                  ->after('payment_method');

            $table->decimal('tip_amount', 8, 2)
                  ->default(0)
                  ->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'discount_amount', 'tip_amount']);
        });
    }
};
