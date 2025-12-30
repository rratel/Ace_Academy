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
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->integer('price')->default(0);
            $table->json('days'); // ['mon', 'wed', 'fri']
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('capacity')->default(20);
            $table->integer('total_sessions')->default(12); // 총 수업 횟수
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
