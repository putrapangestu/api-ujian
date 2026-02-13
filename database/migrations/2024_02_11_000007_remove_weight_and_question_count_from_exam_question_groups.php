<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menghapus weight & question_count dari exam_question_groups
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
        Schema::table('exam_question_groups', function (Blueprint $table) {
            // Hapus kolom yang tidak diperlukan
            $table->dropColumn(['weight', 'question_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_question_groups', function (Blueprint $table) {
            // Tambahkan kembali kolom jika rollback
            $table->integer('weight')->default(1)->after('random_selection');
            $table->integer('question_count')->default(0)->after('weight');
        });
    }
};