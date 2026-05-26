<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->uuid('closure_reason_id')->nullable()->after('agent_id');
            $table->text('summary')->nullable()->after('closure_reason_id');

            $table->foreign('closure_reason_id')
                ->references('id')
                ->on('wa_closure_reasons')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('wa_tickets', function (Blueprint $table) {
            $table->dropForeign(['closure_reason_id']);
            $table->dropColumn(['closure_reason_id', 'summary']);
        });
    }
};
