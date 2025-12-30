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
        // Add indexes to enrollments table
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['student_id', 'lesson_id'], 'enrollments_student_lesson_idx');
            $table->index(['lesson_id', 'status'], 'enrollments_lesson_status_idx');
            $table->index(['status', 'expires_at'], 'enrollments_status_expires_idx');
        });

        // Add indexes to attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'lesson_date'], 'attendances_student_date_idx');
            $table->index(['enrollment_id', 'lesson_date'], 'attendances_enrollment_date_idx');
            $table->index(['lesson_id', 'lesson_date', 'status'], 'attendances_lesson_date_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('enrollments_student_lesson_idx');
            $table->dropIndex('enrollments_lesson_status_idx');
            $table->dropIndex('enrollments_status_expires_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('attendances_student_date_idx');
            $table->dropIndex('attendances_enrollment_date_idx');
            $table->dropIndex('attendances_lesson_date_status_idx');
        });
    }
};
