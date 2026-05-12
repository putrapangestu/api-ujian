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
        Schema::table('question_groups', function (Blueprint $table) {
            if(!Schema::hasColumn('question_groups', 'number_start')) {
                $table->integer('number_start')
                    ->nullable()
                    ->after('display_question_count');
            }

            if(!Schema::hasColumn('question_groups', 'number_end')) {
                $table->integer('number_end')
                    ->nullable()
                    ->after('number_start');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question_groups', function (Blueprint $table) {
            $table->dropColumn(['number_start', 'number_end']);
        });
    }
};
