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
            if (!Schema::hasColumn('question_categories', 'start_range')) {
                $table->integer('start_range')->nullable()->default(0);
            }

            if (!Schema::hasColumn('question_categories', 'end_range')) {
                $table->integer('end_range')->nullable()->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_categories', function (Blueprint $table) {
            if (Schema::hasColumn('question_categories', 'start_range')) {
                $table->dropColumn('start_range');
            }

            if (Schema::hasColumn('question_categories', 'end_range')) {
                $table->dropColumn('end_range');
            }
        });
    }
};
