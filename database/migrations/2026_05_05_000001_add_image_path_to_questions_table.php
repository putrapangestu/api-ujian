<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration untuk menambahkan kolom image_path ke tabel questions
 *
 * Kolom ini digunakan untuk menyimpan path gambar soal yang diupload
 * saat proses import dari file Excel.
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
        Schema::table('questions', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('question_text')
                ->comment('Path gambar soal yang disimpan di storage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('image_path');
        });
    }
};
