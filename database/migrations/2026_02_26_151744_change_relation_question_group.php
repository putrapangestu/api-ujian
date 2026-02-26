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
        //
        Schema::table('question_groups', function (Blueprint $table) {
            if (!Schema::hasColumn('question_groups', 'question_category_id')) {
                $table->foreignUuid('question_category_id')->nullable()->constrained();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
