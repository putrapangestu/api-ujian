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
            $table->id();
            $table->foreignId('user_exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->enum('action', ['start', 'answer', 'navigate', 'submit', 'pause', 'resume']);
            $table->foreignId('question_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('answer')->nullable();
            $table->integer('time_spent')->default(0); // in seconds
            $table->timestamps();
            
            $table->index('user_exam_id');
            $table->index('student_id');
            $table->index('exam_id');
            $table->index('action');
            $table->index('created_at');
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