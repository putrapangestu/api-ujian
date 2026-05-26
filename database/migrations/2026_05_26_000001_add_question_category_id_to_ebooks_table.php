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
        Schema::table('ebooks', function (Blueprint $table) {
            $table->foreignUuid('question_category_id')
                ->nullable()
                ->constrained('question_categories')
                ->nullOnDelete()
                ->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebooks', function (Blueprint $table) {
            $table->dropForeign(['question_category_id']);
            $table->dropColumn('question_category_id');
        });
    }
};
