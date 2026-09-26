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



// ======================================================= Claude:

/*
|--------------------------------------------------------------------------
| Cấu hình đường dẫn cứng - sửa ở đây nếu chuyển sách/đường dẫn khác
|--------------------------------------------------------------------------
*/
$graysAnatomyDir = '/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_pages';

/*
|--------------------------------------------------------------------------
| Trang xem sách
|--------------------------------------------------------------------------
*/
Route::get('/sach/grays-anatomy', function () use ($graysAnatomyDir) {

    if (!is_dir($graysAnatomyDir)) {
        abort(404, "Không tìm thấy thư mục trang đã tách: {$graysAnatomyDir}");
    }

    $files = glob($graysAnatomyDir . '/page-*.pdf');
    natsort($files);
    $totalPages = count($files);

    if ($totalPages === 0) {
        abort(404, 'Thư mục không có file trang nào (page-XXXX.pdf). Chạy pdfseparate trước.');
    }

    // Lấy tỉ lệ khung trang (height / width) từ trang đầu tiên bằng pdfinfo,
    // dùng để dựng khung placeholder đúng tỉ lệ khi cuộn liên tục (tránh giật layout)
    $firstPage = reset($files);
    $aspectRatio = 1.414; // fallback: tỉ lệ A4 dọc
    $info = @shell_exec('pdfinfo ' . escapeshellarg($firstPage) . ' 2>/dev/null');
    if ($info && preg_match('/Page size:\s*([\d.]+)\s*x\s*([\d.]+)/', $info, $m)) {
        $w = (float) $m[1];
        $h = (float) $m[2];
        if ($w > 0) {
            $aspectRatio = $h / $w;
        }
    }

    $viewer = 'pdf-viewer-1';
    $viewer = 'pdf-viewer-2'; //Có div bao bọc qaunh embed, chưa OK lắm
    $viewer = 'pdf-viewer-3'; //OK hơn 2
    $viewer = 'pdf-viewer-5';
    $viewer = 'pdf-viewer-6';
    return view($viewer, [
        'title'       => "Gray's Anatomy",
        'totalPages'  => $totalPages,
        'aspectRatio' => round($aspectRatio, 4),
        'pageUrlBase' => url('/pdf-raw/grays-anatomy'),
    ]);
})->name('pdf.grays-anatomy');



/*
|--------------------------------------------------------------------------
| File PDF của từng trang riêng lẻ (đã tách bằng pdfseparate)
|--------------------------------------------------------------------------
*/
Route::get('/pdf-raw/grays-anatomy/{page}', function ($page) use ($graysAnatomyDir) {
    $page = (int) $page;
    $filename = sprintf('page-%04d.pdf', $page);
    $path = $graysAnatomyDir . '/' . $filename;

    if (!file_exists($path)) {
        abort(404, "Không tìm thấy trang {$page}: {$filename}");
    }

    return response()->file($path, [
        'Content-Type'        => 'application/pdf',
        'Content-Disposition' => 'inline; filename="' . $filename . '"',
    ]);
})->where('page', '[0-9]+')->name('pdf.grays-anatomy.page');



/*
|--------------------------------------------------------------------------
| Hàm dùng chung cho API: trích text từ 1 trang PDF bằng pdftotext (server-side)
|--------------------------------------------------------------------------
*/
function extractPageText(string $path): string
{
    // -layout: cố giữ bố cục gốc (giúp đỡ lẫn 2 cột Anh-Việt hơn so với mặc định)
    $cmd = 'pdftotext -layout ' . escapeshellarg($path) . ' - 2>/dev/null';
    return trim((string) shell_exec($cmd));
}


/*
|--------------------------------------------------------------------------
| API: Dịch nội dung 1 trang
|--------------------------------------------------------------------------
*/
Route::post('/api/pdf/translate', function (Request $request) use ($graysAnatomyDir) {
    $page = (int) $request->input('page');
    $filename = sprintf('page-%04d.pdf', $page);
    $path = $graysAnatomyDir . '/' . $filename;

    if (!file_exists($path)) {
        return response()->json(['error' => "Không tìm thấy trang {$page}"], 404);
    }

    $text = extractPageText($path);
    if ($text === '') {
        return response()->json([
            'error' => 'Không trích được text từ trang này (có thể là trang scan ảnh, không có text layer).',
        ], 422);
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
            'content' => "Dịch đoạn văn y khoa sau sang tiếng Việt, giữ nguyên thuật ngữ giải phẫu chính xác, trình bày rõ ràng, mạch lạc:\n\n{$text}",
        ]],
    ]);

    if ($response->failed()) {
        return response()->json(['error' => 'Lỗi gọi API: ' . $response->body()], 500);
    }

    return response()->json([
        'translated' => $response->json('content.0.text', 'Không có kết quả'),
    ]);
})->name('api.pdf.translate');



/*
|--------------------------------------------------------------------------
| API: Tạo bài giảng từ nội dung 1 trang
|--------------------------------------------------------------------------
*/
Route::post('/api/pdf/lecture', function (Request $request) use ($graysAnatomyDir) {
    $page = (int) $request->input('page');
    $filename = sprintf('page-%04d.pdf', $page);
    $path = $graysAnatomyDir . '/' . $filename;

    if (!file_exists($path)) {
        return response()->json(['error' => "Không tìm thấy trang {$page}"], 404);
    }

    $text = extractPageText($path);
    if ($text === '') {
        return response()->json([
            'error' => 'Không trích được text từ trang này (có thể là trang scan ảnh, không có text layer).',
        ], 422);
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