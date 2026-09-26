<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});



// ======================================================= Gemini:

// 1. Route trả về trang xem sách
Route::get('/read-book', function () {
    // return "hello";
    return view('pdf-viewer-gemini-2');
});

// 2. Route stream file PDF (Hardcode đường dẫn tuyệt đối)
Route::get('/pdf-stream', function () {
    // Đường dẫn tuyệt đối tới file PDF trên ổ D (WSL)
    $filePath = '/mnt/d/00000_Y_SI_DA_KHOA/PDF_FOR_WEB/Grays_Anatomy.pdf';

    if (!file_exists($filePath)) {
        abort(404, 'Không tìm thấy file PDF tại đường dẫn chỉ định.');
    }

    $size = filesize($filePath);
    $file = fopen($filePath, 'rb');

    $headers = [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="book.pdf"',
        'Accept-Ranges' => 'bytes',
    ];

    // Xử lý Byte-Range Request (Rất quan trọng cho file PDF dung lượng lớn)
    if (request()->hasHeader('Range')) {
        $range = request()->header('Range');
        preg_match('/bytes=(\d+)-(\d+)?/', $range, $matches);
        
        $start = intval($matches[1]);
        $end = isset($matches[2]) && $matches[2] !== '' ? intval($matches[2]) : $size - 1;
        $length = $end - $start + 1;

        fseek($file, $start);

        $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size);
        $headers['Content-Length'] = $length;

        return response()->stream(function () use ($file, $length) {
            $buffer = 1024 * 8;
            while (!feof($file) && $length > 0) {
                $read = $length > $buffer ? $buffer : $length;
                echo fread($file, $read);
                flush();
                $length -= $read;
            }
            fclose($file);
        }, 206, $headers);
    }

    // Nếu trình duyệt yêu cầu tải toàn bộ file
    $headers['Content-Length'] = $size;
    return response()->stream(function () use ($file) {
        fpassthru($file);
        fclose($file);
    }, 200, $headers);
});


// Route API nhận Request giải thích trang
Route::post('/api/explain-page', function (Request $request) {
    $page = $request->input('page');
    $text = $request->input('text');

    // Nếu trang PDF không trích xuất được chữ (ví dụ file scan ảnh), có thể thông báo lại
    if (empty(trim($text))) {
        return response()->json([
            'explanation' => "Trang này không chứa dữ liệu văn bản dạng text (có thể là trang ảnh/sơ đồ thuần). Bác sĩ/Bạn có thể dùng tính năng gửi ảnh trang sang Gemini để phân tích hình ảnh."
        ]);
    }

    // Đặt Gemini API Key của bạn vào file .env (GEMINI_API_KEY=your_key)
    $apiKey = env('GEMINI_API_KEY', 'YOUR_API_KEY_HERE');

    $prompt = "Bạn là một chuyên gia Giải phẫu học Y khoa. Hãy giúp tôi dịch và giải thích nội dung trang sách Gray's Anatomy dưới đây sang tiếng Việt dễ hiểu, giải thích rõ các thuật ngữ y học quan trọng:\n\n" . $text;

    // Gọi Gemini API (Sử dụng model gemini-1.5-flash)
    $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ]
    ]);

    if ($response->successful()) {
        $result = $response->json();
        $explanation = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Không nhận được phản hồi hợp lệ từ AI.';
        
        return response()->json([
            'page' => $page,
            'explanation' => $explanation
        ]);
    }

    return response()->json([
        'explanation' => 'Lỗi kết nối tới Gemini API: ' . $response->body()
    ], 500);
});




// ======================================================= Claude:
// ======================================================= Claude:
// ======================================================= Claude:
// Route trả file PDF thô
Route::get('/pdf-raw/grays-anatomy', function () {
    $path = '/mnt/d/00000_Y_SI_DA_KHOA/PDF_FOR_WEB/Grays_Anatomy.pdf';

    if (!file_exists($path)) {
        abort(404, 'Không tìm thấy file PDF tại: ' . $path);
    }

    return response()->file($path, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="Grays_Anatomy.pdf"',
    ]);
})->name('pdf.grays-anatomy.raw');

// Route hiển thị view chứa PDF
Route::get('/sach/grays-anatomy', function () {
    return view('pdf-viewer-claude', [
        'pdfUrl' => route('pdf.grays-anatomy.raw'),
        'title'  => "Gray's Anatomy",
    ]);
})->name('pdf.grays-anatomy');




// ///////////////////////////////
// File PDF thô
Route::get('/pdf-raw/grays-anatomy', function () {
    $path = '/mnt/d/00000_Y_SI_DA_KHOA/PDF_FOR_WEB/Grays_Anatomy.pdf';
    if (!file_exists($path)) {
        abort(404, 'Không tìm thấy file PDF tại: ' . $path);
    }
    return response()->file($path, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="Grays_Anatomy.pdf"',
    ]);
})->name('pdf.grays-anatomy.raw');

// Trang viewer
Route::get('/sach/grays-anatomy', function () {
    return view('pdf-viewer-claude-5', [
        'pdfUrl' => route('pdf.grays-anatomy.raw'),
        'title'  => "Gray's Anatomy",
    ]);
})->name('pdf.grays-anatomy');

// API: dịch nội dung 1 trang
Route::post('/api/pdf/translate', function (Request $request) {
    $text = trim($request->input('text', ''));
    if ($text === '') {
        return response()->json(['error' => 'Không có nội dung để dịch'], 422);
    }

    $response = Http::timeout(60)->withHeaders([
        'x-api-key'         => env('ANTHROPIC_API_KEY'),
        'anthropic-version' => '2023-06-01',
        'content-type'      => 'application/json',
    ])->post('https://api.anthropic.com/v1/messages', [
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 2000,
        'messages'   => [[
            'role'    => 'user',
            'content' => "Dịch đoạn văn y khoa sau sang tiếng Việt, giữ nguyên thuật ngữ giải phẫu chính xác, trình bày rõ ràng:\n\n{$text}",
        ]],
    ]);

    if ($response->failed()) {
        return response()->json(['error' => 'Lỗi gọi API: ' . $response->body()], 500);
    }

    return response()->json([
        'translated' => $response->json('content.0.text', 'Không có kết quả'),
    ]);
})->name('api.pdf.translate');

// API: tạo bài giảng từ nội dung trang
Route::post('/api/pdf/lecture', function (Request $request) {
    $text = trim($request->input('text', ''));
    if ($text === '') {
        return response()->json(['error' => 'Không có nội dung'], 422);
    }

    $response = Http::timeout(90)->withHeaders([
        'x-api-key'         => env('ANTHROPIC_API_KEY'),
        'anthropic-version' => '2023-06-01',
        'content-type'      => 'application/json',
    ])->post('https://api.anthropic.com/v1/messages', [
        'model'      => 'claude-sonnet-4-6',
        'max_tokens' => 3000,
        'messages'   => [[
            'role'    => 'user',
            'content' => "Dựa trên nội dung giải phẫu sau, soạn một bài giảng ngắn gọn cho sinh viên y khoa, có cấu trúc: Mục tiêu bài học, Nội dung chính (gạch đầu dòng), Điểm cần nhớ. Trả lời bằng tiếng Việt, dùng Markdown:\n\n{$text}",
        ]],
    ]);

    if ($response->failed()) {
        return response()->json(['error' => 'Lỗi gọi API: ' . $response->body()], 500);
    }

    return response()->json([
        'lecture' => $response->json('content.0.text', 'Không có kết quả'),
    ]);
})->name('api.pdf.lecture');
