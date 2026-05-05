<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menambahkan kolom display_question_count ke tabel question_groups
 *
 * Kolom ini menentukan berapa soal dari grup ini yang akan ditampilkan/diambil
 * saat proses acak soal ujian berlangsung.
 * Berbeda dengan total_questions (jumlah soal di bank, dihitung otomatis).
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
        Schema::table('question_groups', function (Blueprint $table) {
            $table->integer('display_question_count')->default(0)->after('total_questions')
                ->comment('Jumlah soal yang ditampilkan saat ujian (untuk random selection). 0 = tampilkan semua.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropColumn('display_question_count');
        });
    }
};
