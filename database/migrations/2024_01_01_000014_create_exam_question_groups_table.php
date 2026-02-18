<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk tabel exam_question_groups
 *
 * Migration ini membuat tabel pivot untuk relasi many-to-many antara exams dan question_groups
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
        Schema::create('exam_question_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained();
            $table->foreignUuid('question_group_id')->constrained();
            $table->integer('question_count')->default(0);
            $table->boolean('random_selection')->default(false);
            $table->integer('weight')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_question_groups');
    }
};
