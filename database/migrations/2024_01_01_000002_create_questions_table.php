<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel questions
 *
 * Migration ini membuat tabel questions untuk menyimpan data soal ujian
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
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained();
            $table->text('question_text');
            $table->enum('question_type', ['multiple_choice', 'essay', 'true_false']);
            $table->json('options')->nullable(); // untuk multiple choice
            $table->string('correct_answer')->nullable();
            $table->integer('score')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
