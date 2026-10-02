<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug trích xuất - Trang {{ $pageNumber }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-700 p-6">

    <div class="max-w-5xl mx-auto">
        <div class="flex items-center justify-between mb-4 text-white">
            <h1 class="text-lg font-semibold">Xem thử dữ liệu đã trích xuất - Trang {{ $pageNumber }}</h1>
            <div class="flex gap-2 text-sm">
                <a href="{{ url('/sach/grays-anatomy/debug/' . max(1, $pageNumber - 1)) }}"
                   class="px-3 py-1 bg-gray-800 rounded hover:bg-gray-600">‹ Trang trước</a>
                <a href="{{ url('/sach/grays-anatomy/debug/' . ($pageNumber + 1)) }}"
                   class="px-3 py-1 bg-gray-800 rounded hover:bg-gray-600">Trang sau ›</a>
            </div>
        </div>

        <div class="mb-3 text-xs text-gray-300 space-x-4">
            <span>📝 Số block/line chữ: {{ count($pageData['renderedTest']) }}</span>
            <span>🖍️ Vùng highlight thật: {{ count($pageData['annotHighlightBoxes']) }}</span>
        </div>

        <div class="inline-block shadow-2xl">
            @include('partials.pdf-page-render', ['pageData' => $pageData, 'svgUrl' => $svgUrl])
        </div>
    </div>

</body>
</html>