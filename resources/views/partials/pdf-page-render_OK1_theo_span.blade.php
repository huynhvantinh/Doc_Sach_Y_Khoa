{{--
    PARTIAL TÁI SỬ DỤNG ĐƯỢC - GOM SPAN THEO TỪNG DÒNG (line): mỗi dòng được
    đặt position:absolute theo đúng bbox gốc PDF, còn các span bên trong dòng
    đó nối liền tự nhiên (inline) - để trình duyệt tự tính khoảng cách đúng
    theo font thật nó dùng để render, tránh chữ bị dính/lệch màu như khi định
    vị tuyệt đối từng span riêng lẻ.

    Cách include vào view khác sau này:
        @include('partials.pdf-page-render', ['pageData' => $pageData, 'imageUrlBase' => $imageUrlBase])
--}}
<style>

    .main-page {
        overflow: hidden;
        /* font-family: "Times New Roman", Times, serif; */
    }
    .line {
        line-height: 8px;
    }
</style>

<div class="main-page relative bg-white" style="width: {{ $pageData['renderWidth'] }}px; height: {{ $pageData['renderHeight'] }}px;">

    {{-- Lớp 1: vùng vector có tô màu (khung/ô màu thiết kế sẵn trong layout sách) --}}
    @foreach ($pageData['fillBoxes'] as $box)
        @php
            $bg = 'transparent';
            if ($box['color']) {
                [$r, $g, $b] = array_map(fn($c) => (int) round($c * 255), $box['color']);
                $bg = "rgba({$r},{$g},{$b},0.35)";
            }
        @endphp
        <div class="absolute pointer-events-none" style="
            left: {{ $box['left'] }}px; top: {{ $box['top'] }}px;
            width: {{ $box['width'] }}px; height: {{ $box['height'] }}px;
            background: {{ $bg }};
        "></div>
    @endforeach

    {{-- Lớp 1b: vùng highlight annotation THẬT (bút highlight, khác với ô màu thiết kế sẵn) --}}
    @foreach ($pageData['annotHighlightBoxes'] as $box)
        @php
            $bg = 'rgba(178,235,242,0.55)'; // màu mặc định nếu PDF không lưu màu highlighter cụ thể
            if ($box['color']) {
                [$r, $g, $b] = array_map(fn($c) => (int) round($c * 255), $box['color']);
                $bg = "rgba({$r},{$g},{$b},0.4)";
            }
        @endphp
        <div class="absolute pointer-events-none" style="
            left: {{ $box['left'] }}px; top: {{ $box['top'] }}px;
            width: {{ $box['width'] }}px; height: {{ $box['height'] }}px;
            background: {{ $bg }};
        "></div>
    @endforeach

    {{-- Lớp 2: ảnh minh họa thật --}}
    @foreach ($pageData['images'] as $img)
        <img src="{{ $imageUrlBase }}/{{ $img['number'] }}" alt="Ảnh #{{ $img['number'] }}"
             class="absolute" style="
             left: {{ $img['left'] }}px; top: {{ $img['top'] }}px;
             width: {{ $img['width'] }}px; height: {{ $img['height'] }}px;
             object-fit: contain;">
    @endforeach

    {{-- Lớp 3: đường kẻ nối label -> hình (leader line) --}}
    <svg class="absolute top-0 left-0 pointer-events-none"
         width="{{ $pageData['renderWidth'] }}" height="{{ $pageData['renderHeight'] }}">
        @foreach ($pageData['lines'] as $line)
            @php
                $stroke = 'black';
                if ($line['color']) {
                    [$r, $g, $b] = array_map(fn($c) => (int) round($c * 255), $line['color']);
                    $stroke = "rgb({$r},{$g},{$b})";
                }
            @endphp
            <line x1="{{ $line['x1'] }}" y1="{{ $line['y1'] }}"
                  x2="{{ $line['x2'] }}" y2="{{ $line['y2'] }}"
                  stroke="{{ $stroke }}" stroke-width="1" />
        @endforeach
    </svg>

    {{-- Lớp 4: chữ - mỗi DÒNG (line) 1 khối absolute, span bên trong flow tự nhiên --}}
    @foreach ($pageData['textLines'] as $line)
        <div class="line absolute whitespace-nowrap leading-none" style="
            left: {{ $line['left'] }}px; top: {{ $line['top'] }}px;
        ">
            @foreach ($line['spans'] as $span)
                <span style="
                    font-size: {{ $span['fontSize'] }}px;
                    font-weight: {{ $span['bold'] ? '700' : '400' }};
                    font-style: {{ $span['italic'] ? 'italic' : 'normal' }};
                    color: {{ $span['color'] }};
                    {{ $span['highlighted'] ? 'background: rgba(255,235,59,0.5);' : '' }}
                ">{{ $span['text'] }}</span>
            @endforeach
        </div>
    @endforeach

</div>