<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menambahkan softDelete ke tabel-tabel utama
 * 
 * @package Database\Migrations
 * @author Development Team
 * @version 1.0.0
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabel yang memiliki updated_at
        $tablesWithUpdatedAt = [
            'users',
            'students',
            'student_groups',
            'exams',
            'questions',
            'question_options',
            'question_categories',
            'question_groups',
            'exam_question_groups',
            'user_exams',
            'user_answers',
            'exam_monitoring',
            'personal_access_tokens'
        ];

        foreach ($tablesWithUpdatedAt as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes()->after('updated_at');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Tabel yang memiliki updated_at
        $tablesWithUpdatedAt = [
            'users',
            'students',
            'student_groups',
            'exams',
            'questions',
            'question_options',
            'question_categories',
            'question_groups',
            'exam_question_groups',
            'user_exams',
            'user_answers',
            'exam_monitoring',
            'personal_access_tokens'
        ];

        foreach ($tablesWithUpdatedAt as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};