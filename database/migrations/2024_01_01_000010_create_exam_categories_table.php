<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel exam_categories
 * 
 * Migration ini membuat tabel pivot untuk relasi antara exams dan question_categories
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
        Schema::create('exam_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained('question_categories')->onDelete('cascade');
            $table->integer('weight')->default(1);
            $table->integer('question_count')->default(0);
            $table->timestamps();
            
            $table->unique(['exam_id', 'category_id']);
            $table->index('weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_categories');
    }
};