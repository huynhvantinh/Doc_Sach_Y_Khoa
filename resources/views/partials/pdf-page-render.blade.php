<style>
    .main-page { overflow: hidden; font-family: "Times New Roman", Times, serif; }
    .normal-text-block { line-height: 13px; }
    .layer-bg   { z-index: 0; }
    .layer-hl   { z-index: 1; }
    .layer-text { z-index: 2; }
</style>

<div class="main-page relative bg-white"
     style="width: {{ $pageData['renderWidth'] }}px; height: {{ $pageData['renderHeight'] }}px;">

    {{-- Lớp 0: SVG nền (ảnh + ô màu + đường kẻ, đã bỏ text) --}}
    {{-- <img src="{{ $svgUrl }}" alt=""
         class="absolute top-0 left-0 layer-bg pointer-events-none select-none"
         style="width: {{ $pageData['renderWidth'] }}px; height: {{ $pageData['renderHeight'] }}px;"> --}}
    <div class="absolute top-0 left-0 layer-bg pointer-events-none select-none"
     style="width: {{ $pageData['renderWidth'] }}px;
            height: {{ $pageData['renderHeight'] }}px;
            background: url('{{ $svgUrl }}') no-repeat center / 100% 100%;"></div>

    {{-- Lớp 1: highlight annotation thật (nếu PDF có) --}}
    @foreach ($pageData['annotHighlightBoxes'] as $box)
        @php
            $bg = 'rgba(178,235,242,0.55)';
            if ($box['color']) {
                [$r, $g, $b] = array_map(fn($c) => (int) round($c * 255), $box['color']);
                $bg = "rgba({$r},{$g},{$b},0.4)";
            }
        @endphp
        <div class="absolute layer-hl pointer-events-none" style="
            left: {{ $box['left'] }}px; top: {{ $box['top'] }}px;
            width: {{ $box['width'] }}px; height: {{ $box['height'] }}px;
            background: {{ $bg }};"></div>
    @endforeach

    {{-- Lớp 2: chữ (HTML thật, chọn/tìm được) --}}
    @foreach ($pageData['renderedTest'] as $block)
        <div class="normal-text-block absolute layer-text
                    {{ $pageData['textMode'] === 'block' ? 'text-justify' : 'whitespace-nowrap' }}"
             style="left: {{ $block['left'] }}px; top: {{ $block['top'] }}px;
                    @isset($block['width']) width: {{ $block['width'] }}px; @endisset">
            @foreach ($block['spans'] as $span)
                <span style="
                    font-size: {{ $span['fontSize'] }}px;
                    font-weight: {{ $span['bold'] ? '700' : '400' }};
                    font-style: {{ $span['italic'] ? 'italic' : 'normal' }};
                    color: {{ $span['color'] }};">{{ $span['text'] }}</span>
            @endforeach
        </div>
    @endforeach
</div>