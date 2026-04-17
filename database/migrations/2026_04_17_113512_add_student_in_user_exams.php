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
        Schema::table('user_exams', function (Blueprint $table) {
            if (!Schema::hasColumn('user_exams', 'student_id')) {
                $table->foreignUuid('student_id')->after('exam_id')->nullable()->constrained('students');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_exams', function (Blueprint $table) {

        });
    }
};
