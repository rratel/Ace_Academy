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
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'waitlisted', 'cancelled', 'completed'])->default('pending');
            $table->dateTime('enrolled_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->integer('remaining_sessions')->default(0);
            $table->integer('waitlist_position')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id', 'enrolled_at']);
            $table->index(['branch_id', 'status']);
            $table->index(['lesson_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
