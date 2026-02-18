<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel exam_monitoring
 *
 * Migration ini membuat tabel exam_monitoring untuk monitoring realtime pengerjaan ujian
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
        Schema::create('exam_monitoring', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_exam_id')->constrained();
            $table->foreignUuid('student_id')->constrained();
            $table->foreignUuid('exam_id')->constrained();
            $table->enum('action', ['start', 'answer', 'navigate', 'submit', 'pause', 'resume']);
            $table->foreignUuid('question_id')->nullable()->constrained();
            $table->text('answer')->nullable();
            $table->integer('time_spent')->default(0); // in seconds
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_monitoring');
    }
};
