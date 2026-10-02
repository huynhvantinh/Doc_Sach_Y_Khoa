<?php

namespace App\Helpers;

/**
 * Helper dựng dữ liệu hiển thị (render) 1 trang PDF từ raw_json của PyMuPDF.
 *
 * Toàn bộ là static method (không cần khởi tạo object):
 *     PdfPageHelper::buildPageRenderData($raw, 900);
 *
 * Vị trí: app/Helpers/PdfPageHelper.php  (namespace App\Helpers - PSR-4 tự nạp,
 * KHÔNG cần sửa composer.json).
 */
class PdfPageHelper1
{
    /** Từ khóa trong tên font gợi ý độ đậm cao (Formata-Medium, Sense-Bold...). */
    public const BOLD_KEYWORDS = ['bold', 'medium', 'semibold', 'black', 'heavy', 'helveticaneueltstd-mdcn']; #Font 'helveticaneueltstd-mdcn' có trong trang 34, Test Tổng Hợp

    /** Span nằm từ bao nhiêu % diện tích trong vùng tô màu thì coi là highlight. */
    public const HIGHLIGHT_OVERLAP_THRESHOLD = 0.5;


    /*
    |----------------------------------------------------------------------
    | HÀM CHÍNH: chỉ điều phối, gọi các hàm nhỏ bên dưới.
    |
    | $textMode : 'line' | 'block'  (cách dựng chữ)
    | $options  : bật/tắt nguồn highlight để test
    |     'highlight_from_fill'   => true|false  (fill_drawings có tính là highlight?)
    |     'highlight_from_annots' => true|false  (highlight_annots có tính là highlight?)
    |----------------------------------------------------------------------
    */
    public static function buildPageRenderData(array $raw, int $renderWidth, string $textMode = 'line', array $options = []): array {
        $options += [
            'highlight_from_fill'   => true,
            'highlight_from_annots' => true,
        ];

        $pageWidth    = $raw['page_width']  ?? 612;
        $pageHeight   = $raw['page_height'] ?? 792;
        $scale        = $pageWidth > 0 ? $renderWidth / $pageWidth : 1;
        $renderHeight = $pageHeight * $scale;

        $fillDrawings    = $raw['fill_drawings']    ?? [];
        $highlightAnnots = $raw['highlight_annots'] ?? [];

        $highlightRects = self::collectHighlightRects(
            $fillDrawings,
            $highlightAnnots,
            $options['highlight_from_fill'],
            $options['highlight_from_annots']
        );

        // Giữ tên key 'renderedTest' như view hiện tại đang dùng;
        // 'textMode' cho view biết cấu trúc dữ liệu là 'line' hay 'block'.
        
        $annotHighlightBoxes = self::buildAnnotHighlightBoxes($highlightAnnots, $scale); //Thường rỗng
        $fillBoxes           = self::buildFillBoxes($fillDrawings, $scale);
        $lines               = self::buildLeaderLines($raw['line_drawings'] ?? [], $scale);

        $images              = self::buildImages($raw['images'] ?? [], $scale);
        $renderedTest        = self::buildText($raw, $scale, $highlightRects, $textMode); //Cái phức tạp nhất và có thể cần nhiều phương pháp khác nhau cho các dạng text khác nhau

        
        // dd($annotHighlightBoxes); //[]
        // dd($lines); //Khoảng 14 element - Gồm Là các line label ở hình và các chỗ hilight dòng cũng là line?
        // dd($fillBoxes); //Khoảng 2 phân tử
        // $annotHighlightBoxes = [];
        // $fillBoxes = [];
        // $lines = [];
        

        return compact(
            'pageWidth',
            'pageHeight',
            'scale',
            'renderWidth',
            'renderHeight',
            'textMode',
            'renderedTest',
            'fillBoxes',
            'annotHighlightBoxes',
            'images',
            'lines'
        );
    }


    /*
    |----------------------------------------------------------------------
    | TIỆN ÍCH NHỎ
    |----------------------------------------------------------------------
    */

    /** Tỉ lệ diện tích của $a nằm trong $b (0.0 - 1.0). Rect dạng [x0, y0, x1, y1]. */
    public static function rectOverlapRatio(array $a, array $b): float
    {
        [$ax0, $ay0, $ax1, $ay1] = $a;
        [$bx0, $by0, $bx1, $by1] = $b;

        $interW = max(0, min($ax1, $bx1) - max($ax0, $bx0));
        $interH = max(0, min($ay1, $by1) - max($ay0, $by0));
        $aArea  = max(0, $ax1 - $ax0) * max(0, $ay1 - $ay0);

        return $aArea > 0 ? ($interW * $interH) / $aArea : 0.0;
    }

    /** Font có tên gợi ý độ đậm cao không? */
    public static function isBoldFont(string $fontName): bool
    {
        $fontName = strtolower($fontName);
        foreach (self::BOLD_KEYWORDS as $kw) {
            if (str_contains($fontName, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Màu chữ PyMuPDF (số nguyên sRGB 0xRRGGBB, có thể bị đọc thành số ÂM)
     * -> chuỗi "rgb(r,g,b)".
     */
    public static function intColorToRgb($colorInt): string
    {
        $colorInt = (int) $colorInt;
        if ($colorInt < 0) {
            $colorInt += 0x1000000;
        }
        $r = ($colorInt >> 16) & 0xFF;
        $g = ($colorInt >> 8) & 0xFF;
        $b = $colorInt & 0xFF;

        return "rgb({$r},{$g},{$b})";
    }

    /** Rect [x0,y0,x1,y1] (đơn vị PDF) -> [left, top, width, height] (px đã scale). */
    public static function scaleRect(array $rect, float $scale): array
    {
        [$x0, $y0, $x1, $y1] = $rect;

        return [
            'left'   => $x0 * $scale,
            'top'    => $y0 * $scale,
            'width'  => ($x1 - $x0) * $scale,
            'height' => ($y1 - $y0) * $scale,
        ];
    }


    /*
    |----------------------------------------------------------------------
    | HIGHLIGHT
    |----------------------------------------------------------------------
    */

    /**
     * Gom các hình chữ nhật được coi là "vùng highlight" để so khớp với span.
     * 2 nguồn, bật/tắt riêng để test:
     *  - fill_drawings   : hình khối vector tô màu trong layout (CÓ THỂ dính cả
     *                      nền tiêu đề, thanh caption -> dễ nhận nhầm là highlight)
     *  - highlight_annots: annotation highlight thật của PDF
     */
    public static function collectHighlightRects(
        array $fillDrawings,
        array $highlightAnnots,
        bool $useFill = true,
        bool $useAnnots = true
    ): array {
        $rects = [];

        if ($useFill) {
            foreach ($fillDrawings as $d) {
                if (!empty($d['rect'])) {
                    $rects[] = $d['rect'];
                }
            }
        }
        if ($useAnnots) {
            foreach ($highlightAnnots as $d) {
                if (!empty($d['rect'])) {
                    $rects[] = $d['rect'];
                }
            }
        }

        return $rects;
    }

    /** Span (bbox) có nằm chủ yếu trong 1 vùng highlight nào không? */
    public static function isHighlighted(array $bbox, array $highlightRects): bool
    {
        return false;// thêm vào để TEST ->Bỏ đi (mặc dù đang tính toán sai và hilight sai và vô tội vạ nên để return false lại đúng, do đó cần nghiên cứu lại chỗ này)
        foreach ($highlightRects as $rect) {
            if (self::rectOverlapRatio($bbox, $rect) >= self::HIGHLIGHT_OVERLAP_THRESHOLD) {
                return true;
            }
        }
        return false;
    }


    /*
    |----------------------------------------------------------------------
    | CHỮ: span -> line -> block
    |----------------------------------------------------------------------
    */

    /**
     * Chuyển 1 span thô của PyMuPDF thành mảng sẵn sàng render.
     * Trả về null nếu span rỗng. KHÔNG trim text - giữ nguyên khoảng trắng.
     */
    public static function buildSpan(array $span, float $scale, array $highlightRects): ?array
    {
        $text = $span['text'] ?? '';
        if ($text === '') {
            return null;
        }

        $flags = $span['flags'] ?? 0;

        return [
            'text'        => $text,
            'fontSize'    => ($span['size'] ?? 10) * $scale,
            'bold'        => (bool) ($flags & (1 << 4)) || self::isBoldFont($span['font'] ?? ''),
            'italic'      => (bool) ($flags & (1 << 1)),
            'highlighted' => self::isHighlighted($span['bbox'] ?? [0, 0, 0, 0], $highlightRects),
            'color'       => self::intColorToRgb($span['color'] ?? 0),
        ];
    }

    /** Chỉ lấy các block chữ (type = 0), bỏ block ảnh (type = 1). */
    public static function getTextBlocks(array $raw): array
    {
        return array_values(array_filter(
            $raw['blocks'] ?? [],
            fn($b) => ($b['type'] ?? null) === 0
        ));
    }

    /** Danh sách span đã build của 1 line (bỏ span rỗng). */
    public static function buildSpansOfLine(array $line, float $scale, array $highlightRects): array
    {
        $spans = [];
        foreach ($line['spans'] ?? [] as $span) {
            $built = self::buildSpan($span, $scale, $highlightRects);
            if ($built !== null) {
                $spans[] = $built;
            }
        }
        return $spans;
    }

    /**
     * CÁCH 'line': mỗi line 1 khối, định vị theo bbox của line.
     * Trả về: [['left','top','spans'], ...]
     */
    public static function buildTextByLine(array $raw, float $scale, array $highlightRects): array
    {
        $result = [];
        foreach (self::getTextBlocks($raw) as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                $lineBbox = $line['bbox'] ?? null;
                if (!$lineBbox) {
                    continue;
                }
                $spans = self::buildSpansOfLine($line, $scale, $highlightRects);
                if (!empty($spans)) {
                    $result[] = [
                        'left'  => $lineBbox[0] * $scale,
                        'top'   => $lineBbox[1] * $scale,
                        'spans' => $spans,
                    ];
                }
            }
        }
        return $result;
    }

    /**
     * CÁCH 'block': nối phẳng mọi span của mọi line thành 1 luồng chữ, trình
     * duyệt tự ngắt dòng + justify trong khung width của block.
     * Trả về: [['left','top','width','spans'], ...]
     */
    public static function buildTextByBlock(array $raw, float $scale, array $highlightRects): array
    {
        $result = [];
        foreach (self::getTextBlocks($raw) as $block) {
            $blockBbox = $block['bbox'] ?? null;
            if (!$blockBbox) {
                continue;
            }

            $spansFlat = [];
            foreach ($block['lines'] ?? [] as $line) {
                array_push($spansFlat, ...self::buildSpansOfLine($line, $scale, $highlightRects));
            }

            if (!empty($spansFlat)) {
                $result[] = [
                    'left'  => $blockBbox[0] * $scale,
                    'top'   => $blockBbox[1] * $scale,
                    'width' => ($blockBbox[2] - $blockBbox[0]) * $scale,
                    'spans' => $spansFlat,
                ];
            }
        }
        return $result;
    }

    /** Chọn cách dựng chữ theo $mode. Thêm cách mới chỉ cần thêm 1 nhánh match. */
    public static function buildText(array $raw, float $scale, array $highlightRects, string $mode): array
    {
        return match ($mode) {
            'block' => self::buildTextByBlock($raw, $scale, $highlightRects),
            default => self::buildTextByLine($raw, $scale, $highlightRects),
        };
    }


    /*
    |----------------------------------------------------------------------
    | CÁC LỚP KHÁC: vùng tô màu, highlight annotation, ảnh, đường kẻ nối
    |----------------------------------------------------------------------
    */

    /** Vùng vector có tô màu (ô/khung màu thiết kế sẵn trong layout). */
    public static function buildFillBoxes(array $fillDrawings, float $scale): array
    {
        $boxes = [];
        foreach ($fillDrawings as $d) {
            if (empty($d['rect'])) {
                continue;
            }
            $boxes[] = self::scaleRect($d['rect'], $scale) + ['color' => $d['fill'] ?? null];
        }
        return $boxes;
    }

    /** Vùng highlight annotation thật của PDF. */
    public static function buildAnnotHighlightBoxes(array $highlightAnnots, float $scale): array
    {
        $boxes = [];
        foreach ($highlightAnnots as $d) {
            if (empty($d['rect'])) {
                continue;
            }
            $boxes[] = self::scaleRect($d['rect'], $scale) + ['color' => $d['colors']['stroke'] ?? null];
        }
        return $boxes;
    }

    /** Ảnh minh họa đã trích ra file (bỏ ảnh bbox rỗng hoặc chưa trích được). */
    public static function buildImages(array $rawImages, float $scale): array
    {
        $images = [];
        foreach ($rawImages as $img) {
            $bbox = $img['bbox'] ?? null;
            $isEmptyBbox = !$bbox || ($bbox[0] === $bbox[2] && $bbox[1] === $bbox[3]);
            if ($isEmptyBbox || empty($img['image_ext'])) {
                continue;
            }
            $images[] = self::scaleRect($bbox, $scale) + ['number' => $img['number']];
        }
        return $images;
    }

    /** Đường kẻ nối label -> hình (chỉ lấy các đoạn thẳng "l"). */
    public static function buildLeaderLines(array $lineDrawings, float $scale): array
    {
        $lines = [];
        foreach ($lineDrawings as $d) {
            foreach ($d['items'] ?? [] as $item) {
                if (($item['cmd'] ?? null) !== 'l' || empty($item['points'])) {
                    continue;
                }
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
        return $lines;
    }
}