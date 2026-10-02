<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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









// ///////////////////////////////////////// TEST


/*
|--------------------------------------------------------------------------
| ⚠️ LƯU Ý QUAN TRỌNG - ĐỌC TRƯỚC KHI DÙNG
|--------------------------------------------------------------------------
| File 2 (Python) đang dùng BOOK_SLUG = "grays-anatomy-4th"
| File 3 (Python) đang dùng BOOK_SLUG = "grays-anatomy"
| => 2 giá trị khác nhau sẽ tạo 2 record sách riêng biệt trong bảng `books`,
|    dữ liệu extracted (file 2) và processed (file 3) sẽ KHÔNG liên kết với
|    nhau. Sửa lại cho khớp 1 giá trị duy nhất ở CẢ 3 nơi (file 2, file 3,
|    và biến $bookSlug bên dưới) trước khi chạy lại pipeline.
|--------------------------------------------------------------------------
*/
 
 
/*
|--------------------------------------------------------------------------
| Hàm dùng chung: build dữ liệu đã tính toán sẵn (đơn vị px, đã scale theo
| khung hiển thị) từ 1 bản ghi raw_json thô của PyMuPDF.
|
| Đây CHỈ LÀ CÔNG CỤ XEM THỬ (debug) để kiểm tra dữ liệu trích xuất có đầy đủ
| hay không - KHÔNG phải bước phân loại paragraph/label như file 3 Python.
| Hàm này hiển thị "phẳng" mọi thứ có trong raw_json (mọi span chữ, mọi vùng
| tô màu, mọi ảnh, mọi đường kẻ) đúng nguyên vị trí gốc trong PDF.
|--------------------------------------------------------------------------
*/
function buildPageRenderData(array $raw, int $renderWidth): array
{
    $pageWidth  = $raw['page_width']  ?? 612;
    $pageHeight = $raw['page_height'] ?? 792;
    $scale      = $pageWidth > 0 ? $renderWidth / $pageWidth : 1;
    $renderHeight = $pageHeight * $scale;
 
    $fillDrawings   = $raw['fill_drawings']   ?? [];
    $highlightAnnots = $raw['highlight_annots'] ?? [];
 
    // --- Hàm tính tỉ lệ diện tích giao nhau (giống hệt logic file 3 Python) ---
    $rectOverlapRatio = function (array $a, array $b): float {
        [$ax0, $ay0, $ax1, $ay1] = $a;
        [$bx0, $by0, $bx1, $by1] = $b;
        $ix0 = max($ax0, $bx0);
        $iy0 = max($ay0, $by0);
        $ix1 = min($ax1, $bx1);
        $iy1 = min($ay1, $by1);
        $interArea = max(0, $ix1 - $ix0) * max(0, $iy1 - $iy0);
        $aArea = max(0, $ax1 - $ax0) * max(0, $ay1 - $ay0);
        return $aArea > 0 ? $interArea / $aArea : 0.0;
    };
 
    // QUAN TRỌNG: tô nền trong PDF có thể đến từ 2 nguồn khác nhau -
    // 1. fill_drawings: hình khối vector thiết kế sẵn trong layout (ô màu, khung...)
    // 2. highlight_annots: annotation highlight THẬT (giống bút highlight tay),
    //    thường trải dài qua nhiều dòng/đoạn văn theo hình dạng bất thường.
    // Trước đây chỉ kiểm tra (1), bỏ sót (2) - khiến những đoạn bị highlight thật
    // trong PDF (như đoạn "abdominal cavity is entirely continuous...") bị mất
    // hoàn toàn khi hiển thị. Giờ kiểm tra CẢ HAI nguồn.
    $allHighlightRects = [];
    foreach ($fillDrawings as $d) {
        if (!empty($d['rect'])) {
            $allHighlightRects[] = $d['rect'];
        }
    }
    foreach ($highlightAnnots as $d) {
        if (!empty($d['rect'])) {
            $allHighlightRects[] = $d['rect'];
        }
    }
 
    $isHighlighted = function (array $bbox) use ($allHighlightRects, $rectOverlapRatio): bool {
        foreach ($allHighlightRects as $rect) {
            if ($rectOverlapRatio($bbox, $rect) >= 0.5) {
                return true;
            }
        }
        return false;
    };
 
    // --- Nhận diện bold: kết hợp CẢ 2 nguồn ---
    // 1. Bit "bold" chuẩn trong flags (PDF/font đánh dấu chính thức là biến thể Bold)
    // 2. Tên font có chứa từ khóa gợi ý độ đậm cao (Medium/Semibold/Black/Heavy...)
    //    - một số font gia đình (như Formata-Medium) là 1 file font riêng, đậm hơn
    //    Regular rõ rệt, nhưng KHÔNG được đánh dấu bit bold trong metadata.
    $boldKeywords = ['bold', 'medium', 'semibold', 'black', 'heavy'];
 
    $isBoldFont = function (string $fontName) use ($boldKeywords): bool {
        $fontName = strtolower($fontName);
        foreach ($boldKeywords as $kw) {
            if (str_contains($fontName, $kw)) {
                return true;
            }
        }
        return false;
    };
 
    // PyMuPDF mã hóa màu chữ thành 1 số nguyên sRGB (0xRRGGBB), có thể bị đọc
    // thành số ÂM khi PHP json_decode do vượt quá phạm vi int 32-bit có dấu -
    // cần cộng lại 0x1000000 để quy về đúng giá trị dương trước khi tách kênh màu.
    $intColorToRgb = function ($colorInt): string {
        $colorInt = (int) $colorInt;
        if ($colorInt < 0) {
            $colorInt += 0x1000000;
        }
        $r = ($colorInt >> 16) & 0xFF;
        $g = ($colorInt >> 8) & 0xFF;
        $b = $colorInt & 0xFF;
        return "rgb({$r},{$g},{$b})";
    };
 
    // --- 1. GOM SPAN THEO BLOCK - nối phẳng mọi line thành 1 luồng chữ liên tục ---
    // Khác với lần thử "gom theo block" trước đó (đã thất bại vì vẫn ép mỗi
    // LINE gốc vào 1 <div> riêng bên trong, dễ tràn/vỡ khi độ rộng dòng gốc
    // không khớp font trình duyệt): lần này bỏ hẳn khái niệm "dòng gốc PDF" -
    // nối tất cả span của mọi line trong block thành 1 chuỗi span phẳng duy
    // nhất, để trình duyệt tự động ngắt dòng lại từ đầu theo đúng width của
    // block (dùng text-align: justify để dàn đều 2 bên, giống văn bản in).
    // Vì text mỗi line trong raw_json đã có sẵn khoảng trắng cuối dòng (do
    // PyMuPDF giữ nguyên), nối liền không cần thêm dấu cách thủ công.
    $textBlocks = [];
    foreach ($raw['blocks'] ?? [] as $block) {
        if (($block['type'] ?? null) !== 0) { // 0 = text block, 1 = image block
            continue;
        }
        $blockBbox = $block['bbox'] ?? null;
        if (!$blockBbox) {
            continue;
        }
 
        $spansFlat = [];
        foreach ($block['lines'] ?? [] as $line) {
            foreach ($line['spans'] ?? [] as $span) {
                $text = $span['text'] ?? '';
                if ($text === '') {
                    continue; // chỉ bỏ span thực sự rỗng, KHÔNG trim - giữ nguyên
                }             // khoảng trắng để chữ không bị dính liền nhau
 
                $bbox = $span['bbox'] ?? [0, 0, 0, 0];
                $flags = $span['flags'] ?? 0;
                $fontName = $span['font'] ?? '';
                $bold = (bool) ($flags & (1 << 4)) || $isBoldFont($fontName);
 
                $spansFlat[] = [
                    'text'        => $text,
                    'fontSize'    => ($span['size'] ?? 10) * $scale,
                    'bold'        => $bold,
                    'italic'      => (bool) ($flags & (1 << 1)),
                    'highlighted' => $isHighlighted($bbox),
                    'color'       => $intColorToRgb($span['color'] ?? 0),
                ];
            }
        }
 
        if (!empty($spansFlat)) {
            $textBlocks[] = [
                'left'  => $blockBbox[0] * $scale,
                'top'   => $blockBbox[1] * $scale,
                'width' => ($blockBbox[2] - $blockBbox[0]) * $scale,
                'spans' => $spansFlat,
            ];
        }
    }
 
    // --- 2. Vùng vector có tô màu (để thấy các ô/khung màu thiết kế sẵn) ---
    $fillBoxes = [];
    foreach ($fillDrawings as $d) {
        if (empty($d['rect'])) {
            continue;
        }
        [$x0, $y0, $x1, $y1] = $d['rect'];
        $fillBoxes[] = [
            'left'   => $x0 * $scale,
            'top'    => $y0 * $scale,
            'width'  => ($x1 - $x0) * $scale,
            'height' => ($y1 - $y0) * $scale,
            'color'  => $d['fill'] ?? null,
        ];
    }
 
    // --- 2b. Vùng highlight annotation thật (đường viền bất thường theo từng dòng) ---
    $annotHighlightBoxes = [];
    foreach ($highlightAnnots as $d) {
        if (empty($d['rect'])) {
            continue;
        }
        [$x0, $y0, $x1, $y1] = $d['rect'];
        $stroke = $d['colors']['stroke'] ?? null; // màu highlighter thật nếu PDF có lưu
        $annotHighlightBoxes[] = [
            'left'   => $x0 * $scale,
            'top'    => $y0 * $scale,
            'width'  => ($x1 - $x0) * $scale,
            'height' => ($y1 - $y0) * $scale,
            'color'  => $stroke, // mảng [r,g,b] từ 0-1, hoặc null -> dùng màu mặc định
        ];
    }
 
    // --- 3. Ảnh minh họa thật (đã trích ra file ở bước file 2) ---
    $images = [];
    foreach ($raw['images'] ?? [] as $img) {
        $bbox = $img['bbox'] ?? null;
        $isEmptyBbox = !$bbox || ($bbox[0] === $bbox[2] && $bbox[1] === $bbox[3]);
        if ($isEmptyBbox || empty($img['image_ext'])) {
            continue; // bbox rỗng hoặc chưa trích được ảnh (xref=0 và không bật fallback)
        }
        $images[] = [
            'number' => $img['number'],
            'left'   => $bbox[0] * $scale,
            'top'    => $bbox[1] * $scale,
            'width'  => ($bbox[2] - $bbox[0]) * $scale,
            'height' => ($bbox[3] - $bbox[1]) * $scale,
        ];
    }
 
    // --- 4. Đường kẻ nối label -> hình (leader line) ---
    $lines = [];
    foreach ($raw['line_drawings'] ?? [] as $d) {
        foreach ($d['items'] ?? [] as $item) {
            if (($item['cmd'] ?? null) === 'l' && !empty($item['points'])) {
                [$p1, $p2] = $item['points'];
                $lines[] = [
                    'x1'    => $p1[0] * $scale,
                    'y1'    => $p1[1] * $scale,
                    'x2'    => $p2[0] * $scale,
                    'y2'    => $p2[1] * $scale,
                    'color' => $d['color'] ?? null,
                ];
            }
        }
    }
    // dd($lines);
    // $lines = [];
 
    return compact(
        'pageWidth',
        'pageHeight',
        'scale',
        'renderWidth',
        'renderHeight',
        'textBlocks',
        'fillBoxes',
        'annotHighlightBoxes',
        'images',
        'lines'
    );
}
 
 
/*
|--------------------------------------------------------------------------
| Route xem thử: tái hiện lại 1 trang từ raw_json bằng HTML (để kiểm tra
| dữ liệu trích xuất đã đầy đủ chưa, trước khi thật sự gọi API dịch).
|--------------------------------------------------------------------------
*/
Route::get('/sach/grays-anatomy/debug/{page}', function ($page) {
    $bookSlug = 'grays-anatomy-4th'; // TODO: sửa cho khớp đúng slug đang dùng ở file 2/file 3 Python
 
    $book = DB::table('books')->where('slug', $bookSlug)->first();
    if (!$book) {
        abort(404, "Không tìm thấy sách với slug='{$bookSlug}'. Kiểm tra lại đã chạy file 1 (Python) chưa.");
    }
 
    $row = DB::table('pages')
        ->where('book_id', $book->id)
        ->where('page_number', (int) $page)
        ->first();
 
    if (!$row) {
        abort(404, "Chưa có record cho trang {$page} trong bảng pages.");
    }
    if (!$row->raw_json) {
        abort(404, "Trang {$page} chưa có raw_json (chưa chạy file 2 Python cho trang này).");
    }
 
    $raw = json_decode($row->raw_json, true);
    if ($raw === null) {
        abort(500, "raw_json của trang {$page} bị lỗi, không parse được JSON.");
    }
 
    $pageData = buildPageRenderData($raw, 900); // 900px chiều rộng khung hiển thị
 
    return view('pdf-page-debug', [
        'pageNumber'   => (int) $page,
        'pageData'     => $pageData,
        'imageUrlBase' => url("/images-raw/grays-anatomy/{$page}"),
    ]);
})->where('page', '[0-9]+')->name('debug.pdf.page');
 
 
/*
|--------------------------------------------------------------------------
| Route phục vụ ảnh đã trích ra từ file 2 (nằm ngoài thư mục public, nên
| không thể trỏ trực tiếp - phải qua route riêng để đọc và trả về file).
|--------------------------------------------------------------------------
*/
Route::get('/images-raw/grays-anatomy/{page}/{number}', function ($page, $number) {
    $imagesDir = '/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images';
 
    $pattern = sprintf('%s/page-%04d_img%s.*', $imagesDir, (int) $page, $number);
    $matches = glob($pattern);
 
    if (empty($matches)) {
        abort(404, "Không tìm thấy ảnh: page={$page}, number={$number}");
    }
 
    $path = $matches[0];
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpeg', 'jpg' => 'image/jpeg',
        'png'         => 'image/png',
        'jp2', 'jpx'  => 'image/jp2',
        default       => 'application/octet-stream',
    };
 
    return response()->file($path, ['Content-Type' => $mime]);
})->where(['page' => '[0-9]+', 'number' => '[0-9]+'])->name('images.raw.grays-anatomy');
 