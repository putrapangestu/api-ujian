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
        Schema::table('user_exams', function (Blueprint $table) {
            $table->integer('current_score')->default(0)->after('score')->comment('Skor sementara realtime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_exams', function (Blueprint $table) {
            $table->dropColumn('current_score');
        });
    }
};
