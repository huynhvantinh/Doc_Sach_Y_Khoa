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
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->unsignedInteger('page_number'); // Số trang trong sách (1-based)

            $table->json('raw_json')->nullable();
            $table->json('processed_json')->nullable();
            $table->json('response_json')->nullable();
 
            // Trạng thái xử lý - để quản lý tiến độ 1299 trang, hỗ trợ resume khi lỗi
            $table->enum('status', [
                'skip_translation',      // Trang không cần dịch (bìa, mục lục, trang trắng...)
                'skip_lecture',          // Trang không cần tạo bài giảng (bìa, mục lục, trang trắng... hoặc trang quá ngắn)
                'pending',      // Chưa làm gì
                'extracted',    // Đã có raw_json
                'processed',    // Đã có processed_json, sẵn sàng gửi API
                'translating',  // Đang gọi API (tránh chạy trùng khi có nhiều worker)
                'translated',   // Đã có response_json
                'failed',       // Lỗi ở bước nào đó, xem error_message
                'reviewed',     // Đã kiểm tra tay, chốt bản dịch
            ])->default('pending');
 
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
 
            $table->timestamps();
 
            $table->unique(['book_id', 'page_number']);
            $table->index('status');


            // Đường dẫn file PDF của riêng trang này (đã tách bằng PyMuPDF)
            // $table->string('pdf_path')->nullable(); //lấy đường dẫn bên tabel books
 
            // Đường dẫn các file JSON qua từng giai đoạn xử lý
            // $table->string('raw_json_path')->nullable();       // Output thô từ get_text("dict") + get_images
            // $table->string('processed_json_path')->nullable(); // Đã tách paragraphs[]/labels[], có markup + bbox%
            // $table->string('response_json_path')->nullable();  // Kết quả trả về từ API dịch (Gemini/Claude)
            // $table->string('bai_giang')->nullable();  // Kết quả trả về từ API dịch (Gemini/Claude) -> lưu bên table riêng


            // // Theo dõi chi phí gọi API dịch
            // $table->string('api_model', 50)->nullable();
            // $table->unsignedInteger('input_tokens')->nullable();
            // $table->unsignedInteger('output_tokens')->nullable();
            // $table->decimal('api_cost_usd', 10, 6)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
