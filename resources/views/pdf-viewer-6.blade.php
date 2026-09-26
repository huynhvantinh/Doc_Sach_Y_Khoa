<!DOCTYPE html>
<html lang="vi" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        /* Toàn trang web không bao giờ có thanh cuộn - chỉ 2 khung PDF được phép cuộn */
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        .pdf-panel embed {
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
            
            {{-- Ô nhập Zoom (Thay thế span zoomLabel) --}}
            <input id="zoomInput" type="text" placeholder="Vừa trang"
                class="w-20 text-center bg-gray-950 border border-gray-700 rounded px-1 py-1 text-gray-200 focus:outline-none focus:border-blue-500">
            
            <button id="btnZoomIn" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600 w-7">+</button>
            <button id="btnFitPage" class="px-2 py-1 rounded bg-gray-700 hover:bg-gray-600">Vừa trang</button>

            <div class="flex-1"></div>
            <span id="loadingIndicator" class="text-gray-500 hidden">Đang tải...</span>
        </div>

        {{-- ===================== 2 KHUNG PDF ===================== --}}
        <div class="flex-1 min-h-0 relative flex">

            <div class="pdf-panel relative w-1/2 h-full overflow-hidden border-r border-gray-700 bg-gray-600">
                <div
                    {{-- class="absolute top-1.5 left-1.5 z-10 text-[11px] bg-black/60 text-gray-100 px-1.5 py-0.5 rounded pointer-events-none"> --}}
                    class="absolute top-1.5 left-1/2 z-10 text-[11px] bg-black/60 text-gray-100 px-1.5 py-0.5 rounded pointer-events-none">
                    {{-- 🇻🇳 Tiếng Việt · Trang <span id="pageLabelVi">1</span> --}}
                    Trang <span id="pageLabelVi">1</span>
                </div>
                <div id="embedHolderVi" class="w-full h-full"></div>
            </div>

            <div class="pdf-panel relative w-1/2 h-full overflow-hidden bg-gray-600">
                <div
                    {{-- class="absolute top-1.5 left-1.5 z-10 text-[11px] bg-black/60 text-gray-100 px-1.5 py-0.5 rounded pointer-events-none"> --}}
                    class="absolute top-1.5 left-1/2 z-10 text-[11px] bg-black/60 text-gray-100 px-1.5 py-0.5 rounded pointer-events-none">
                    {{-- 🇬🇧 Tiếng Anh · Trang <span id="pageLabelEn">1</span> --}}
                    Trang <span id="pageLabelEn">1</span>
                </div>
                <div id="embedHolderEn" class="w-full h-full"></div>
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
        $(function() {

            /* ============ CẤU HÌNH TỪ SERVER ============ */
            const TOTAL_PAGES = {{ $totalPages }};
            const PAGE_URL_BASE = @json($pageUrlBase); // /pdf-raw/grays-anatomy
            const TRANSLATE_URL = @json(route('api.pdf.translate'));
            const LECTURE_URL = @json(route('api.pdf.lecture'));
            const CSRF = $('meta[name=csrf-token]').attr('content');

            /* ============ STATE ============ */
            let currentPage = 1;
            let zoomPercent = null; // null = "Vừa trang" (view=Fit); số = phần trăm zoom thủ công

            const $holderVi = $('#embedHolderVi');
            const $holderEn = $('#embedHolderEn');

            /* ============ TIỆN ÍCH ============ */
            function buildSrc(n) {
                const base = `${PAGE_URL_BASE}/${n}`;
                if (zoomPercent === null) {
                    return `${base}#toolbar=0&navpanes=0&view=Fit`;
                }
                return `${base}#toolbar=0&navpanes=0&zoom=${zoomPercent}`;
            }

            // QUAN TRỌNG: luôn xóa <embed> cũ và tạo phần tử <embed> HOÀN TOÀN MỚI thay vì
            // chỉ đổi thuộc tính src. Chrome đôi khi không nạp lại plugin PDF nếu chỉ đổi
            // phần #hash của URL (view=Fit / zoom=...), khiến khung bị trống sau khi bấm
            // "Vừa trang". Tạo lại phần tử mới đảm bảo plugin luôn được khởi tạo sạch.
            function renderEmbed($holder, src) {
                $holder.empty();
                const $embed = $('<embed>', {
                    type: 'application/pdf',
                    src: src,
                });
                $holder.append($embed);
            }

            function refreshBothPanels() {
                const src = buildSrc(currentPage);
                renderEmbed($holderVi, src); // TODO: sau này đổi sang route file tiếng Việt riêng
                renderEmbed($holderEn, src);
                updateZoomInputUI();
            }

            function updateZoomInputUI() {
                if (zoomPercent === null) {
                    $('#zoomInput').val('Vừa trang');
                } else {
                    $('#zoomInput').val(zoomPercent + '%');
                }
            }

            function applyZoomFromInput() {
                let val = $('#zoomInput').val().trim();
                
                // Lọc lấy các chữ số trong chuỗi nhập vào
                let num = parseInt(val.replace(/[^\d]/g, ''), 10);

                if (isNaN(num) || val === '') {
                    zoomPercent = null;
                } else {
                    // Giới hạn zoom từ 20% đến 500% để đảm bảo không bị lỗi hiển thị
                    zoomPercent = Math.min(Math.max(num, 20), 500);
                }
                
                refreshBothPanels();
            }

            function loadPage(n) {
                currentPage = Math.min(Math.max(n, 1), TOTAL_PAGES);
                $('#pageInput').val(currentPage);
                $('#pageLabelVi, #pageLabelEn').text(currentPage);
                refreshBothPanels();
            }

            /* ============ GẮN SỰ KIỆN ============ */
            $('#btnPrev').on('click', () => loadPage(currentPage - 1));
            $('#btnNext').on('click', () => loadPage(currentPage + 1));

            
            /* ============ BẮT SỰ KIỆN Ô NHẬP SỐ TRANG ============ */
            $('#pageInput')
                .on('focus', function() {
                    // Tự động bôi đen toàn bộ nội dung khi click hoặc focus vào ô input
                    $(this).select();
                })
                // .on('keypress', function(e) {
                //     if (e.which === 13) { // Phím Enter
                //         const n = parseInt($(this).val(), 10);
                //         if (n >= 1 && n <= TOTAL_PAGES) {
                //             loadPage(n);
                //         }
                //         $(this).blur();  // Nhả focus khỏi input
                //         window.focus();  // Trả focus về window chính để dùng phím mũi tên
                //     }
                // })
                .on('change', function() {
                    const n = parseInt($(this).val(), 10);
                    if (n >= 1 && n <= TOTAL_PAGES) {
                        loadPage(n);
                        $(this).blur();  // Nhả focus khỏi input
                        window.focus();  // Trả focus về window chính để dùng phím mũi tên
                    }
                });
            // $('#pageInput').on('change', function() {
            //     const n = parseInt($(this).val(), 10);
            //     if (n >= 1 && n <= TOTAL_PAGES) loadPage(n);
            // });



            // Xử lý sự kiện cho ô nhập Zoom
            $('#zoomInput')
                .on('focus', function() {
                    // Khi click vào ô input, bôi đen toàn bộ nội dung để dễ gõ đè
                    $(this).select();
                })
                .on('keypress', function(e) {
                    if (e.which === 13) { // Phím Enter
                        applyZoomFromInput();
                        $(this).blur();
                    }
                })
                .on('blur', function() {
                    applyZoomFromInput();
                });

            $('#btnZoomIn').on('click', () => {
                zoomPercent = zoomPercent === null ? 110 : Math.min(400, zoomPercent + 10);
                refreshBothPanels();
            });
            
            $('#btnZoomOut').on('click', () => {
                zoomPercent = zoomPercent === null ? 90 : Math.max(30, zoomPercent - 10);
                refreshBothPanels();
            });
            
            $('#btnFitPage').on('click', () => {
                zoomPercent = null;
                refreshBothPanels();
            });

            /* ============ GỌI API: DỊCH / TẠO BÀI GIẢNG ============ */
            let lastResultText = '';

            function openModal(title) {
                $('#modalTitle').text(title);
                $('#modalBody').text('Đang xử lý, vui lòng đợi...');
                $('#modalOverlay').removeClass('hidden').addClass('flex');
            }

            function closeModal() {
                $('#modalOverlay').addClass('hidden').removeClass('flex');
            }
            $('#btnCloseModal, #btnCloseModal2').on('click', closeModal);
            $('#modalOverlay').on('click', function(e) {
                if (e.target.id === 'modalOverlay') closeModal();
            });
            $('#btnCopyResult').on('click', () => {
                navigator.clipboard.writeText(lastResultText || '');
            });

            function callApi(url, page) {
                return $.ajax({
                    url: url,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': CSRF
                    },
                    data: JSON.stringify({
                        page
                    }),
                });
            }

            $('#btnTranslate').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                openModal(`Bản dịch — Trang ${currentPage}`);
                callApi(TRANSLATE_URL, currentPage)
                    .done((data) => {
                        lastResultText = data.translated || data.error || 'Không có kết quả';
                        $('#modalBody').text(lastResultText);
                    })
                    .fail((xhr) => {
                        $('#modalBody').text('Lỗi: ' + (xhr.responseJSON?.error || xhr.statusText));
                    })
                    .always(() => $btn.prop('disabled', false));
            });

            $('#btnLecture').on('click', function() {
                const $btn = $(this).prop('disabled', true);
                openModal(`Bài giảng — Trang ${currentPage}`);
                callApi(LECTURE_URL, currentPage)
                    .done((data) => {
                        lastResultText = data.lecture || data.error || 'Không có kết quả';
                        $('#modalBody').text(lastResultText);
                    })
                    .fail((xhr) => {
                        $('#modalBody').text('Lỗi: ' + (xhr.responseJSON?.error || xhr.statusText));
                    })
                    .always(() => $btn.prop('disabled', false));
            });

            /* ============ KHỞI ĐỘNG ============ */
            loadPage(1);



            // ///////////////////// 
            /* ============ BẮT SỰ KIỆN PHÍM MŨI TÊN (LEFT / RIGHT) ============ */
            $(document).on('keydown', function(e) {
                // Nếu người dùng đang gõ số trang hoặc gõ zoom thì không chuyển trang
                const activeElem = document.activeElement;
                const isInputActive = activeElem && (
                    activeElem.tagName === 'INPUT' || 
                    activeElem.tagName === 'TEXTAREA' || 
                    activeElem.isContentEditable
                );

                if (isInputActive) return;

                // Phím mũi tên Trái (ArrowLeft) -> Trang trước
                // if (e.key === 'ArrowLeft' || e.keyCode === 37) {
                if (e.key === 'ArrowLeft' || e.keyCode === 37 || e.key === 'ArrowUp' || e.keyCode === 38) {
                    e.preventDefault(); // Tránh cuộn trang
                    loadPage(currentPage - 1);
                } 
                // Phím mũi tên Phải (ArrowRight) -> Trang sau
                // else if (e.key === 'ArrowRight' || e.keyCode === 39) {
                else if (e.key === 'ArrowRight' || e.keyCode === 39 || e.key === 'ArrowDown' || e.keyCode === 40) {
                    e.preventDefault(); // Tránh cuộn trang
                    loadPage(currentPage + 1);
                }
            });

            /* ============ GIỮ FOCUS NGOÀI EMBED KHI DI CHUỘT/CLICK VÀO PDF ============ */
            // Tự động trả focus về window chính khi di chuột vào hoặc click vào vùng chứa PDF
            // $('.pdf-panel').on('mouseenter click', function() {
            $('.pdf-panel').on('mouseenter mouseleave click', function() {
                console.log("333");
                window.focus();
            });

        });
    </script>

</body>

</html>
