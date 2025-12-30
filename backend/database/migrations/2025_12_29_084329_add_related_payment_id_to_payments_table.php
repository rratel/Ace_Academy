<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('related_payment_id')->nullable()->after('processed_by')
                ->constrained('payments')->nullOnDelete();
            $table->text('notes')->nullable()->after('related_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['related_payment_id']);
            $table->dropColumn(['related_payment_id', 'notes']);
        });
    }
};
