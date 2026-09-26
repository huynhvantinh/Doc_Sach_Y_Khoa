<!DOCTYPE html>
<html lang="vi" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        /* Thanh cuộn gọn cho 2 khung PDF (giờ là overflow thật của DOM, không phải của plugin) */
        .pdf-panel::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .pdf-panel::-webkit-scrollbar-track {
            background: #374151;
        }

        .pdf-panel::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 5px;
        }

        .pdf-panel::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .pdf-page-wrapper embed {
            width: 100%;
            height: 100%;
            display: block;
            border: none;
        }
    </style>
</head>

<body class="h-full bg-gray-800 text-gray-200 font-sans text-sm">

    <div class="h-full flex flex-col">

        {{-- ===================== TOOLBAR ===================== --}}
        <div class="h-10 shrink-0 bg-gray-900 border-b border-gray-700 flex items-center gap-3 px-3 text-xs">

            <span class="font-semibold text-white truncate max-w-[160px] hidden sm:inline">{{ $title }}</span>
            <div class="w-px h-5 bg-gray-700 hidden sm:block"></div>

            <button id="btnPrev" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600">‹ Trước</button>
            <div class="flex items-center gap-1">
                <input id="pageInput" type="number" min="1" value="1"
                    class="w-14 text-center bg-gray-950 border border-gray-700 rounded px-1 py-1">
                <span class="text-gray-400">/ {{ $totalPages }}</span>
            </div>
            <button id="btnNext" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600">Sau ›</button>

            <div class="w-px h-5 bg-gray-700"></div>

            {{-- Zoom bằng nút: resize div bọc, KHÔNG reload embed, không mất vị trí đang xem --}}
            <button id="btnZoomOut" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 w-7">−</button>
            <span id="zoomLabel" class="text-gray-400 w-12 text-center">100%</span>
            <button id="btnZoomIn" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 w-7">+</button>
            <button id="btnFitPage" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600">Vừa trang</button>

            <div class="flex-1"></div>
            <span class="text-gray-500 text-[11px] hidden md:inline">
                Mẹo: giữ chuột giữa rồi rê để tự cuộn khi đã zoom lớn
            </span>
        </div>

        {{-- ===================== 2 KHUNG PDF ===================== --}}
        <div class="flex-1 min-h-0 relative flex">

            <div id="panelVi"
                class="pdf-panel relative w-1/2 h-full overflow-auto flex border-r border-gray-700 bg-gray-600">
                <div
                    class="absolute top-1.5 left-1.5 z-10 text-[11px] bg-black/60 text-gray-100 px-1.5 py-0.5 rounded pointer-events-none">
                    🇻🇳 Tiếng Việt · Trang <span id="pageLabelVi">1</span>
                </div>
                <div id="wrapperVi" class="pdf-page-wrapper m-auto shrink-0">
                    <embed id="embedVi" type="application/pdf">
                </div>
            </div>

            <div id="panelEn" class="pdf-panel relative w-1/2 h-full overflow-auto flex bg-gray-600">
                <div
                    class="absolute top-1.5 left-1.5 z-10 text-[11px] bg-black/60 text-gray-100 px-1.5 py-0.5 rounded pointer-events-none">
                    🇬🇧 Tiếng Anh · Trang <span id="pageLabelEn">1</span>
                </div>
                <div id="wrapperEn" class="pdf-page-wrapper m-auto shrink-0">
                    <embed id="embedEn" type="application/pdf">
                </div>
            </div>

            <div class="absolute top-2 right-2 flex flex-col gap-2 z-20">
                <button id="btnTranslate"
                    class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-2 rounded-md shadow-lg whitespace-nowrap">
                    🌐 Dịch trang
                </button>
                <button id="btnLecture"
                    class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-3 py-2 rounded-md shadow-lg whitespace-nowrap">
                    📘 Bài giảng
                </button>
            </div>

        </div>
    </div>

    {{-- ===================== MODAL KẾT QUẢ ===================== --}}
    <div id="modalOverlay" class="hidden fixed inset-0 bg-black/60 z-50 items-center justify-center">
        <div
            class="bg-white text-gray-800 w-[min(760px,90vw)] max-h-[82vh] rounded-lg shadow-2xl flex flex-col overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 bg-gray-100 border-b border-gray-200">
                <h3 id="modalTitle" class="text-sm font-semibold">Kết quả</h3>
                <button id="btnCloseModal" class="text-gray-500 hover:text-gray-800 text-lg leading-none">✕</button>
            </div>
            <div id="modalBody" class="p-4 overflow-y-auto text-sm leading-relaxed whitespace-pre-wrap"></div>
            <div class="px-4 py-2 border-t border-gray-200 bg-gray-50 flex justify-end gap-2">
                <button id="btnCopyResult"
                    class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-100">Sao
                    chép</button>
                <button id="btnCloseModal2"
                    class="text-xs px-3 py-1.5 rounded border border-gray-300 bg-white hover:bg-gray-100">Đóng</button>
            </div>
        </div>
    </div>

    <script>
        /* ============ CẤU HÌNH TỪ SERVER ============ */
        const TOTAL_PAGES = {{ $totalPages }};
        const ASPECT_RATIO = {{ $aspectRatio }}; // height / width của trang (dùng để tính khung vừa trang)
        const PAGE_URL_BASE = @json($pageUrlBase);
        const TRANSLATE_URL = @json(route('api.pdf.translate'));
        const LECTURE_URL = @json(route('api.pdf.lecture'));
        const CSRF = document.querySelector('meta[name=csrf-token]').content;

        /* ============ STATE ============ */
        let currentPage = 1;
        let zoomFactor = 1.0; // 1.0 = vừa khít trong khung (baseline "Vừa trang")

        const panelVi = document.getElementById('panelVi');
        const panelEn = document.getElementById('panelEn');
        const wrapperVi = document.getElementById('wrapperVi');
        const wrapperEn = document.getElementById('wrapperEn');
        const embedVi = document.getElementById('embedVi');
        const embedEn = document.getElementById('embedEn');

        /* ============ TÍNH KÍCH THƯỚC ============ */
        // Kích thước "vừa khít" (contain) trong 1 khung, giữ đúng tỉ lệ trang
        function computeBaseSize(panelEl) {
            const w = panelEl.clientWidth - 16; // trừ chút lề
            const h = panelEl.clientHeight - 16;
            if (w * ASPECT_RATIO <= h) {
                return {
                    width: w,
                    height: Math.round(w * ASPECT_RATIO)
                };
            }
            return {
                width: Math.round(h / ASPECT_RATIO),
                height: h
            };
        }

        function applySize() {
            [
                [panelVi, wrapperVi],
                [panelEn, wrapperEn]
            ].forEach(([panelEl, wrapperEl]) => {
                const base = computeBaseSize(panelEl);
                wrapperEl.style.width = Math.round(base.width * zoomFactor) + 'px';
                wrapperEl.style.height = Math.round(base.height * zoomFactor) + 'px';
            });
            document.getElementById('zoomLabel').textContent = Math.round(zoomFactor * 100) + '%';
        }

        /* ============ ĐIỀU HƯỚNG TRANG (chỉ đổi src khi đổi TRANG, không đổi khi zoom) ============ */
        function pageUrl(n) {
            return `${PAGE_URL_BASE}/${n}#toolbar=0&navpanes=0`;
        }

        function loadPage(n) {
            currentPage = Math.min(Math.max(n, 1), TOTAL_PAGES);
            document.getElementById('pageInput').value = currentPage;
            document.getElementById('pageLabelVi').textContent = currentPage;
            document.getElementById('pageLabelEn').textContent = currentPage;

            // TODO: sau này khi có file PDF tiếng Việt riêng, đổi embedVi.src sang route khác
            embedVi.src = pageUrl(currentPage);
            embedEn.src = pageUrl(currentPage);
        }

        /* ============ SỰ KIỆN ============ */
        document.getElementById('btnPrev').onclick = () => loadPage(currentPage - 1);
        document.getElementById('btnNext').onclick = () => loadPage(currentPage + 1);
        document.getElementById('pageInput').onchange = (e) => {
            const n = parseInt(e.target.value, 10);
            if (n >= 1 && n <= TOTAL_PAGES) loadPage(n);
        };

        document.getElementById('btnZoomIn').onclick = () => {
            zoomFactor = Math.min(4, zoomFactor * 1.15);
            applySize();
        };
        document.getElementById('btnZoomOut').onclick = () => {
            zoomFactor = Math.max(0.3, zoomFactor / 1.15);
            applySize();
        };
        document.getElementById('btnFitPage').onclick = () => {
            zoomFactor = 1.0;
            applySize();
        };

        // Khung đổi kích thước (resize cửa sổ, đổi layout) -> tính lại "vừa trang" theo tỉ lệ hiện tại
        window.addEventListener('resize', applySize);

        /* ============ GỌI API: DỊCH / TẠO BÀI GIẢNG ============ */
        let lastResultText = '';

        function openModal(title) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalBody').textContent = 'Đang xử lý, vui lòng đợi...';
            const overlay = document.getElementById('modalOverlay');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
        }

        function closeModal() {
            const overlay = document.getElementById('modalOverlay');
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }
        document.getElementById('btnCloseModal').onclick = closeModal;
        document.getElementById('btnCloseModal2').onclick = closeModal;
        document.getElementById('modalOverlay').addEventListener('click', (e) => {
            if (e.target.id === 'modalOverlay') closeModal();
        });
        document.getElementById('btnCopyResult').onclick = () => {
            navigator.clipboard.writeText(lastResultText || '');
        };

        async function callApi(url, page) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({
                    page
                }),
            });
            return res.json();
        }

        document.getElementById('btnTranslate').onclick = async () => {
            const btn = document.getElementById('btnTranslate');
            btn.disabled = true;
            openModal(`Bản dịch — Trang ${currentPage}`);
            try {
                const data = await callApi(TRANSLATE_URL, currentPage);
                lastResultText = data.translated || data.error || 'Không có kết quả';
                document.getElementById('modalBody').textContent = lastResultText;
            } catch (err) {
                document.getElementById('modalBody').textContent = 'Lỗi: ' + err.message;
            } finally {
                btn.disabled = false;
            }
        };

        document.getElementById('btnLecture').onclick = async () => {
            const btn = document.getElementById('btnLecture');
            btn.disabled = true;
            openModal(`Bài giảng — Trang ${currentPage}`);
            try {
                const data = await callApi(LECTURE_URL, currentPage);
                lastResultText = data.lecture || data.error || 'Không có kết quả';
                document.getElementById('modalBody').textContent = lastResultText;
            } catch (err) {
                document.getElementById('modalBody').textContent = 'Lỗi: ' + err.message;
            } finally {
                btn.disabled = false;
            }
        };

        /* ============ KHỞI ĐỘNG ============ */
        applySize();
        loadPage(1);
    </script>
</body>

</html>
