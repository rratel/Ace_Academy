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
        // Modify enrollments table
        Schema::table('enrollments', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['user_id']);

            // Rename column
            $table->renameColumn('user_id', 'student_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            // Add new foreign key constraint
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });

        // Modify attendances table
        Schema::table('attendances', function (Blueprint $table) {
            // Drop the old foreign key
            $table->dropForeign(['user_id']);

            // Rename column
            $table->renameColumn('user_id', 'student_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            // Add new foreign key constraint
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert attendances table
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->renameColumn('student_id', 'user_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // Revert enrollments table
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->renameColumn('student_id', 'user_id');
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
