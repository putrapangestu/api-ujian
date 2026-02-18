<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk membuat tabel question_exam
 * Menyimpan soal yang dipilih dari bank soal untuk ujian
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
        Schema::create('question_exam', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained();
            $table->foreignUuid('question_id')->constrained();
            $table->foreignUuid('question_group_id')->constrained();
            $table->integer('question_number'); // Nomor urut soal (1, 2, 3, dst)
            $table->integer('question_order'); // Urutan random untuk setiap student
            $table->integer('start_range')->nullable(); // Nomor mulai untuk grup soal
            $table->integer('end_range')->nullable(); // Nomor akhir untuk grup soal
            $table->integer('weight')->default(1); // Bobot soal dalam ujian
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_exam');
    }
};
