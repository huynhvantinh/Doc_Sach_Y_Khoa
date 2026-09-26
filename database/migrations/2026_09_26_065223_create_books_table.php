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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');                     // Tên sách, VD: "Gray's Anatomy for Students"
            $table->string('slug')->unique();             // Dùng cho URL, VD: "grays-anatomy"
            $table->string('source_pdf_path')->nullable(); // Đường dẫn tới file PDF gốc đầy đủ
            $table->unsignedInteger('total_pages')->default(0);
            // $table->string('language_source', 10)->default('en'); // Ngôn ngữ gốc
            // $table->string('language_target', 10)->default('vi'); // Ngôn ngữ đích
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
