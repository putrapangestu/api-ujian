<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menghapus start_range, end_range, dan weight dari exam_question
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
        Schema::table('question_exam', function (Blueprint $table) {
            // Hapus kolom yang tidak diperlukan
            $table->dropColumn(['start_range', 'end_range', 'weight']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_exam', function (Blueprint $table) {
            // Tambahkan kembali kolom jika rollback
            $table->integer('start_range')->nullable()->after('question_order');
            $table->integer('end_range')->nullable()->after('start_range');
            $table->integer('weight')->default(1)->after('end_range');
        });
    }
};