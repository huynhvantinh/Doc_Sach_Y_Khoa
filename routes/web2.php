<?php

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
 
    $fillDrawings = $raw['fill_drawings'] ?? [];
 
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
 
    $isHighlighted = function (array $bbox) use ($fillDrawings, $rectOverlapRatio): bool {
        foreach ($fillDrawings as $d) {
            if (empty($d['rect'])) {
                continue;
            }
            if ($rectOverlapRatio($bbox, $d['rect']) >= 0.5) {
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
 
    // --- 1. Các span chữ (đoạn văn + label, chưa phân loại - chỉ hiển thị thô) ---
    $textElements = [];
    foreach ($raw['blocks'] ?? [] as $block) {
        if (($block['type'] ?? null) !== 0) { // 0 = text block, 1 = image block
            continue;
        }
        foreach ($block['lines'] ?? [] as $line) {
            foreach ($line['spans'] ?? [] as $span) {
                $text = $span['text'] ?? '';
                if (trim($text) === '') {
                    continue;
                }
                $bbox = $span['bbox'] ?? [0, 0, 0, 0];
                $flags = $span['flags'] ?? 0;
                $fontName = $span['font'] ?? '';
 
                $bold = (bool) ($flags & (1 << 4)) || $isBoldFont($fontName);
 
                $textElements[] = [
                    'text'        => $text,
                    'left'        => $bbox[0] * $scale,
                    'top'         => $bbox[1] * $scale,
                    'width'       => ($bbox[2] - $bbox[0]) * $scale,
                    'height'      => ($bbox[3] - $bbox[1]) * $scale,
                    'fontSize'    => ($span['size'] ?? 10) * $scale,
                    'bold'        => $bold,
                    'italic'      => (bool) ($flags & (1 << 1)),
                    'highlighted' => $isHighlighted($bbox),
                ];
            }
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
 
    return compact(
        'pageWidth',
        'pageHeight',
        'scale',
        'renderWidth',
        'renderHeight',
        'textElements',
        'fillBoxes',
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
    // $imagesDir = '/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images';
    $imagesDir = '/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images_rgb';
 
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
