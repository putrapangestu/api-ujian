<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('question_categories', function (Blueprint $table) {
            if (Schema::hasIndex('question_categories', 'question_categories_code_unique')) {
                $table->dropUnique('question_categories_code_unique');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_categories', function (Blueprint $table) {
            if (!Schema::hasIndex('question_categories', 'question_categories_code_unique')) {
                $table->unique('code', 'question_categories_code_unique');
            }
        });
    }
};
