<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menambahkan question_option_id ke user_answer
 * dan membuat answer nullable
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
        Schema::table('user_answers', function (Blueprint $table) {
            // Tambahkan question_option_id
            $table->unsignedBigInteger('question_option_id')->nullable()->after('question_id');
            
            // Buat answer nullable
            $table->string('answer')->nullable()->change();
            
            // Foreign key constraint untuk question_option_id
            $table->foreign('question_option_id')
                  ->references('id')
                  ->on('question_options')
                  ->onDelete('cascade');
            
            // Index untuk performance
            $table->index('question_option_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_answers', function (Blueprint $table) {
            // Hapus foreign key constraint
            $table->dropForeign(['question_option_id']);
            
            // Hapus index
            $table->dropIndex(['question_option_id']);
            
            // Hapus kolom question_option_id
            $table->dropColumn('question_option_id');
            
            // Kembalikan answer menjadi not nullable
            $table->string('answer')->nullable(false)->change();
        });
    }
};