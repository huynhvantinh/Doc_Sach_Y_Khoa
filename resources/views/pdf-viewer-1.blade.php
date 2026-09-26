<!DOCTYPE html>
<html lang="vi" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Không cho phép trang web cuộn - chỉ khung PDF được cuộn */
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        #pdf-scroll-area::-webkit-scrollbar {
            width: 10px;
        }

        #pdf-scroll-area::-webkit-scrollbar-track {
            background: #374151;
        }

        #pdf-scroll-area::-webkit-scrollbar-thumb {
            background: #6b7280;
            border-radius: 5px;
        }

        #pdf-scroll-area::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .page-slot embed {
            width: 100%;
            height: 100%;
            display: block;
            border: none;
        }
    </style>
</head>

<body class="h-full bg-gray-800 text-gray-200 font-sans text-sm">

    <div class="h-full flex flex-col">

        {{-- ===================== TOOLBAR (cố định, gọn) ===================== --}}
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

            <button id="btnZoomOut" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 w-7">−</button>
            <span id="zoomLabel" class="text-gray-400 w-10 text-center">100%</span>
            <button id="btnZoomIn" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 w-7">+</button>
            <button id="btnFitWidth" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600">Vừa khung</button>

            <div class="w-px h-5 bg-gray-700"></div>

            <div class="flex items-center bg-gray-950 border border-gray-700 rounded overflow-hidden">
                <button id="modeSingle" class="px-2 py-1 bg-blue-600 text-white">1 trang</button>
                <button id="modeContinuous" class="px-2 py-1 hover:bg-gray-700">Cuộn liên tục</button>
            </div>

            <div class="flex-1"></div>
            <span id="loadingIndicator" class="text-gray-500 hidden">Đang tải...</span>
        </div>

        {{-- ===================== VÙNG XEM PDF (duy nhất có thanh cuộn) ===================== --}}
        <div class="flex-1 relative min-h-0">

            <div id="pdf-scroll-area" class="absolute inset-0 overflow-y-auto overflow-x-hidden bg-gray-600">
                <div id="pdf-container" class="flex flex-col items-center gap-3 py-4"></div>
            </div>

            <div class="absolute top-3 right-3 flex flex-col gap-2 z-20">
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
        //============ CẤU HÌNH TỪ SERVER ============
        const TOTAL_PAGES = {{ $totalPages }};
        const ASPECT_RATIO = {{ $aspectRatio }}; // height / width của trang
        const PAGE_URL_BASE = @json($pageUrlBase); // /pdf-raw/grays-anatomy
        const TRANSLATE_URL = @json(route('api.pdf.translate'));
        const LECTURE_URL = @json(route('api.pdf.lecture'));
        const CSRF = document.querySelector('meta[name=csrf-token]').content;

        // /* ============ STATE ============ */
        let mode = 'single'; // 'single' | 'continuous'
        let currentPage = 1;
        let pageWidthPx = 800; // zoom điều khiển qua biến này, dùng chung cho cả 2 mode

        const container = document.getElementById('pdf-container');
        const scrollArea = document.getElementById('pdf-scroll-area');

        let slots = {}; // page -> { el, mounted }
        let observer = null;

        /* ============ TIỆN ÍCH ============ */
        function pageUrl(n) {
            return `${PAGE_URL_BASE}/${n}#toolbar=0&navpanes=0&view=FitH`;
        }

        function slotSize() {
            return {
                w: pageWidthPx,
                h: Math.round(pageWidthPx * ASPECT_RATIO)
            };
        }

        function applySizeToAllSlots() {
            const {
                w,
                h
            } = slotSize();
            Object.values(slots).forEach(s => {
                s.el.style.width = w + 'px';
                s.el.style.height = h + 'px';
            });
            document.getElementById('zoomLabel').textContent = Math.round((pageWidthPx / 800) * 100) + '%';
        }

        function makeSlot(n) {
            const {
                w,
                h
            } = slotSize();
            const div = document.createElement('div');
            div.className = 'page-slot relative bg-white shadow-lg shrink-0';
            div.style.width = w + 'px';
            div.style.height = h + 'px';
            div.dataset.page = n;

            const badge = document.createElement('div');
            badge.className = 'absolute -top-5 left-0 text-[11px] text-gray-400 select-none';
            badge.textContent = `Trang ${n}`;
            div.appendChild(badge);

            return div;
        }

        function mountEmbed(n) {
            const slot = slots[n];
            if (!slot || slot.mounted) return;
            const embed = document.createElement('embed');
            embed.type = 'application/pdf';
            embed.src = pageUrl(n);
            slot.el.appendChild(embed);
            slot.mounted = true;
        }

        function unmountEmbed(n) {
            const slot = slots[n];
            if (!slot || !slot.mounted) return;
            const embed = slot.el.querySelector('embed');
            if (embed) embed.remove();
            slot.mounted = false;
        }

        function setCurrentPage(n, syncInput = true) {
            currentPage = Math.min(Math.max(n, 1), TOTAL_PAGES);
            if (syncInput) document.getElementById('pageInput').value = currentPage;
        }

        /* ============ CHẾ ĐỘ: 1 TRANG (Next/Prev để chuyển, không cuộn) ============ */
        function buildSingleMode() {
            if (observer) {
                observer.disconnect();
                observer = null;
            }
            container.innerHTML = '';
            slots = {};

            const slot = makeSlot(currentPage);
            container.appendChild(slot);
            slots[currentPage] = {
                el: slot,
                mounted: false
            };
            mountEmbed(currentPage);

            scrollArea.scrollTop = 0;
        }

        function goToPageSingle(n) {
            setCurrentPage(n);
            buildSingleMode();
        }

        /* ============ CHẾ ĐỘ: CUỘN LIÊN TỤC (tự phát hiện trang qua IntersectionObserver) ============ */
        function buildContinuousMode() {
            container.innerHTML = '';
            slots = {};

            for (let n = 1; n <= TOTAL_PAGES; n++) {
                const slot = makeSlot(n);
                container.appendChild(slot);
                slots[n] = {
                    el: slot,
                    mounted: false
                };
            }

            const visibleRatios = {};

            observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const n = parseInt(entry.target.dataset.page, 10);
                    if (entry.isIntersecting) {
                        mountEmbed(n);
                        visibleRatios[n] = entry.intersectionRatio;
                    } else {
                        unmountEmbed(n);
                        delete visibleRatios[n];
                    }
                });

                // Trang chiếm tỉ lệ hiển thị lớn nhất trong khung nhìn -> dùng làm "trang hiện tại"
                let bestPage = null,
                    bestRatio = 0;
                for (const [p, r] of Object.entries(visibleRatios)) {
                    if (r > bestRatio) {
                        bestRatio = r;
                        bestPage = parseInt(p, 10);
                    }
                }
                if (bestPage) setCurrentPage(bestPage);
            }, {
                root: scrollArea,
                rootMargin: '600px 0px 600px 0px', // tải trước / giữ lại các trang gần khung nhìn
                threshold: [0, 0.25, 0.5, 0.75, 1],
            });

            Object.values(slots).forEach(s => observer.observe(s.el));

            requestAnimationFrame(() => {
                const target = slots[currentPage]?.el;
                if (target) target.scrollIntoView({
                    block: 'start'
                });
            });
        }

        function goToPageContinuous(n) {
            setCurrentPage(n);
            const target = slots[n]?.el;
            if (target) target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        /* ============ ĐIỀU KHIỂN CHUNG ============ */
        function rebuildViewer() {
            if (mode === 'single') buildSingleMode();
            else buildContinuousMode();
        }

        function switchMode(newMode) {
            if (mode === newMode) return;
            mode = newMode;

            document.getElementById('modeSingle').className =
                'px-2 py-1 ' + (mode === 'single' ? 'bg-blue-600 text-white' : 'hover:bg-gray-700');
            document.getElementById('modeContinuous').className =
                'px-2 py-1 ' + (mode === 'continuous' ? 'bg-blue-600 text-white' : 'hover:bg-gray-700');

            rebuildViewer();
        }

        function fitWidth() {
            const padding = 40; // lề trái/phải trong khung cuộn
            pageWidthPx = Math.max(300, scrollArea.clientWidth - padding);
            applySizeToAllSlots();
        }

        /* ============ GẮN SỰ KIỆN ============ */
        document.getElementById('btnPrev').onclick = () => {
            const n = currentPage - 1;
            if (n < 1) return;
            mode === 'single' ? goToPageSingle(n) : goToPageContinuous(n);
        };
        document.getElementById('btnNext').onclick = () => {
            const n = currentPage + 1;
            if (n > TOTAL_PAGES) return;
            mode === 'single' ? goToPageSingle(n) : goToPageContinuous(n);
        };
        document.getElementById('pageInput').onchange = (e) => {
            const n = parseInt(e.target.value, 10);
            if (n >= 1 && n <= TOTAL_PAGES) {
                mode === 'single' ? goToPageSingle(n) : goToPageContinuous(n);
            }
        };

        document.getElementById('btnZoomIn').onclick = () => {
            pageWidthPx = Math.min(2200, Math.round(pageWidthPx * 1.15));
            applySizeToAllSlots();
        };
        document.getElementById('btnZoomOut').onclick = () => {
            pageWidthPx = Math.max(300, Math.round(pageWidthPx / 1.15));
            applySizeToAllSlots();
        };
        document.getElementById('btnFitWidth').onclick = fitWidth;

        document.getElementById('modeSingle').onclick = () => switchMode('single');
        document.getElementById('modeContinuous').onclick = () => switchMode('continuous');

        /* ============ GỌI API: DỊCH / TẠO BÀI GIẢNG (server tự trích text bằng pdftotext) ============ */
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
        fitWidth();
        buildSingleMode(); // mặc định mở ở chế độ 1 trang
    </script>
</body>

</html>
