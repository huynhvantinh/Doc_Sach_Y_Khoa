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
        Schema::create('lectures', function (Blueprint $table) {
            $table->id();

            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();

            $table->longText('content_raw')->nullable();   // Nội dung bài giảng gốc do API trả về
            $table->longText('content_html')->nullable();  // Nội dung bài giảng, đã render HTML
            // $table->string('response_json_path')->nullable(); // Response gốc từ API (backup, debug)

            $table->enum('status', [
                'generating', // Đang gọi API tạo bài giảng
                'completed',  // Đã tạo xong
                'failed',
            ])->default('generating');

            $table->text('error_message')->nullable();

            $table->timestamps(); // created_at dùng để biết bài giảng nào mới nhất

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lectures');
    }
};
