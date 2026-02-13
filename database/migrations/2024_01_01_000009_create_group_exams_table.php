<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel group_exams
 * 
 * Migration ini membuat tabel pivot untuk relasi antara student_groups dan exams
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
        Schema::create('group_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('student_groups')->onDelete('cascade');
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->date('assigned_date');
            $table->date('due_date')->nullable();
            $table->timestamps();
            
            $table->unique(['group_id', 'exam_id']);
            $table->index('assigned_date');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_exams');
    }
};