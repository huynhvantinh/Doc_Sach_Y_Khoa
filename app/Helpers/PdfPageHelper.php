<?php

namespace App\Helpers;

class PdfPageHelper
{
    public const BOLD_KEYWORDS = ['bold', 'medium', 'semibold', 'black', 'heavy', 'helveticaneueltstd-mdcn'];

    /**
     * $textMode: 'line' | 'block'
     */
    public static function buildPageRenderData(array $raw, int $renderWidth, string $textMode = 'line'): array
    {
        $pageWidth    = $raw['page_width']  ?? 612;
        $pageHeight   = $raw['page_height'] ?? 792;
        $scale        = $pageWidth > 0 ? $renderWidth / $pageWidth : 1;
        $renderHeight = $pageHeight * $scale;

        $annotHighlightBoxes = self::buildAnnotHighlightBoxes($raw['highlight_annots'] ?? [], $scale);
        $renderedTest        = self::buildText($raw, $scale, $textMode);

        return compact(
            'pageWidth', 'pageHeight', 'scale',
            'renderWidth', 'renderHeight',
            'textMode', 'renderedTest', 'annotHighlightBoxes'
        );
    }

    public static function isBoldFont(string $fontName): bool
    {
        $fontName = strtolower($fontName);
        foreach (self::BOLD_KEYWORDS as $kw) {
            if (str_contains($fontName, $kw)) return true;
        }
        return false;
    }

    public static function intColorToRgb($colorInt): string
    {
        $colorInt = (int) $colorInt;
        if ($colorInt < 0) $colorInt += 0x1000000;
        return sprintf('rgb(%d,%d,%d)', ($colorInt >> 16) & 0xFF, ($colorInt >> 8) & 0xFF, $colorInt & 0xFF);
    }

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

    /* ---------------- CHỮ ---------------- */

    public static function buildSpan(array $span, float $scale): ?array
    {
        $text = $span['text'] ?? '';
        if ($text === '') return null;

        $flags = $span['flags'] ?? 0;

        return [
            'text'     => $text,
            'fontSize' => ($span['size'] ?? 10) * $scale,
            'bold'     => (bool) ($flags & (1 << 4)) || self::isBoldFont($span['font'] ?? ''),
            'italic'   => (bool) ($flags & (1 << 1)),
            'color'    => self::intColorToRgb($span['color'] ?? 0),
        ];
    }

    public static function getTextBlocks(array $raw): array
    {
        return array_values(array_filter(
            $raw['blocks'] ?? [],
            fn($b) => ($b['type'] ?? null) === 0
        ));
    }

    public static function buildSpansOfLine(array $line, float $scale): array
    {
        $spans = [];
        foreach ($line['spans'] ?? [] as $span) {
            $built = self::buildSpan($span, $scale);
            if ($built !== null) $spans[] = $built;
        }
        return $spans;
    }

    public static function buildTextByLine(array $raw, float $scale): array
    {
        $result = [];
        foreach (self::getTextBlocks($raw) as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                $bbox = $line['bbox'] ?? null;
                if (!$bbox) continue;
                $spans = self::buildSpansOfLine($line, $scale);
                if ($spans) {
                    $result[] = ['left' => $bbox[0] * $scale, 'top' => $bbox[1] * $scale, 'spans' => $spans];
                }
            }
        }
        return $result;
    }

    public static function buildTextByBlock(array $raw, float $scale): array
    {
        $result = [];
        foreach (self::getTextBlocks($raw) as $block) {
            $bbox = $block['bbox'] ?? null;
            if (!$bbox) continue;

            $flat = [];
            foreach ($block['lines'] ?? [] as $line) {
                array_push($flat, ...self::buildSpansOfLine($line, $scale));
            }
            if ($flat) {
                $result[] = [
                    'left'  => $bbox[0] * $scale,
                    'top'   => $bbox[1] * $scale,
                    'width' => ($bbox[2] - $bbox[0]) * $scale,
                    'spans' => $flat,
                ];
            }
        }
        return $result;
    }

    public static function buildText(array $raw, float $scale, string $mode): array
    {
        return match ($mode) {
            'block' => self::buildTextByBlock($raw, $scale),
            default => self::buildTextByLine($raw, $scale),
        };
    }

    /* ---------------- HIGHLIGHT ANNOTATION THẬT ---------------- */

    public static function buildAnnotHighlightBoxes(array $highlightAnnots, float $scale): array
    {
        $boxes = [];
        foreach ($highlightAnnots as $d) {
            if (empty($d['rect'])) continue;
            $boxes[] = self::scaleRect($d['rect'], $scale) + ['color' => $d['colors']['stroke'] ?? null];
        }
        return $boxes;
    }
}