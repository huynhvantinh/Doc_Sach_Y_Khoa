{{--
    PARTIAL TÁI SỬ DỤNG ĐƯỢC: nhận vào $pageData (đã build sẵn qua buildPageRenderData())
    và $imageUrlBase, vẽ lại 1 trang PDF bằng HTML thuần túy.

    Cách include vào view khác sau này:
        @include('partials.pdf-page-render', ['pageData' => $pageData, 'imageUrlBase' => $imageUrlBase])

    Thứ tự các lớp (layer) từ dưới lên: vùng tô màu -> ảnh -> đường kẻ nối -> chữ,
    để chữ luôn nằm trên cùng, dễ đọc.
--}}
<div class="relative bg-white" style="width: {{ $pageData['renderWidth'] }}px; height: {{ $pageData['renderHeight'] }}px;">

    {{-- Lớp 1: vùng vector có tô màu (thấy được các ô/khung màu thiết kế sẵn trong sách) --}}
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

    {{-- Lớp 4: chữ (đoạn văn + label - CHƯA phân loại, chỉ hiển thị thô để kiểm tra) --}}
    @foreach ($pageData['textElements'] as $el)
        <div class="absolute whitespace-nowrap leading-none" style="
            left: {{ $el['left'] }}px; top: {{ $el['top'] }}px;
            font-size: {{ $el['fontSize'] }}px;
            font-weight: {{ $el['bold'] ? '700' : '400' }};
            font-style: {{ $el['italic'] ? 'italic' : 'normal' }};
            {{ $el['highlighted'] ? 'background: rgba(255,235,59,0.5);' : '' }}
            color: #111827;
        ">{{ $el['text'] }}</div>
    @endforeach

</div>