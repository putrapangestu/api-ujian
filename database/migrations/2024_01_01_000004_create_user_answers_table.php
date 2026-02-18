<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel user_answers
 *
 * Migration ini membuat tabel user_answers untuk menyimpan jawaban user
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
        Schema::create('user_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_exam_id')->constrained();
            $table->foreignUuid('question_id')->constrained();
            $table->text('answer');
            $table->boolean('is_correct')->default(false);
            $table->integer('score')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
