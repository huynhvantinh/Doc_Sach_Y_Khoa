<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đọc Sách Y Khoa & AI Assistant</title>
    <!-- Tailwind CSS qua CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- PDF.js CSS & Library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf_viewer.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        body { 
            background-color: #121212; 
            color: #e0e0e0; 
            font-family: system-ui, -apple-system, sans-serif; 
        }

        /* Khung chứa trang PDF */
        .pdf-page-container {
            position: relative;
            margin: 0 auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.7);
            background-color: #ffffff;
        }

        /* Canvas vẽ nét chữ gốc từ PDF */
        #pdf-render {
            display: block;
            width: 100%;
            height: 100%;
        }

        /* Lớp TextLayer để bôi đen/copy chữ */
        .textLayer {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
            opacity: 1 !important;
            line-height: 1.0;
        }

        /* GIỮ NGUYÊN CHẤT LƯỢNG PDF: 
           Ẩn hoàn toàn nét chữ trùng lập của TextLayer để không bị đè chữ,
           chỉ dùng TextLayer cho mục đích bôi đen/copy */
        .textLayer span,
        .textLayer ::selection {
            color: transparent !important;
            fill: transparent !important;
        }

        /* Màu highlight khi người dùng bôi đen chữ */
        .textLayer ::selection {
            background: rgba(6, 182, 212, 0.35) !important;
        }

        /* Style thanh cuộn đẹp mắt */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #18181b; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #52525b; }
    </style>
</head>
<body class="h-screen w-screen flex flex-col overflow-hidden">

    <!-- 1. HEADER / TOOLBAR -->
    <header class="h-14 bg-zinc-900 border-b border-zinc-800 flex items-center justify-between px-6 shrink-0 z-10">
        <div class="flex items-center space-x-4">
            <h1 class="font-semibold text-lg text-emerald-400">📖 Y Khoa Reader</h1>
            <span class="text-sm text-zinc-400">| Gray's Anatomy</span>
        </div>

        <!-- Điều khiển Trang -->
        <div class="flex items-center space-x-3 bg-zinc-800 px-4 py-1.5 rounded-lg border border-zinc-700">
            <button id="prev-page" class="hover:bg-zinc-700 px-2.5 py-1 rounded text-sm transition">◄ Trước</button>
            <span class="text-sm">Trang <input type="number" id="page-num" value="1" class="w-14 text-center bg-zinc-900 border border-zinc-700 rounded text-white py-0.5"> / <span id="page-count">--</span></span>
            <button id="next-page" class="hover:bg-zinc-700 px-2.5 py-1 rounded text-sm transition">Sau ►</button>
        </div>

        <!-- Nút tương tác AI -->
        <div class="flex items-center space-x-3">
            <button id="btn-explain" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-lg font-medium text-sm transition flex items-center space-x-2">
                <span>✨ Giải thích & Dịch trang này</span>
            </button>
        </div>
    </header>

    <!-- 2. NỘI DUNG CHÍNH -->
    <main class="flex-1 flex overflow-hidden">
        <!-- Khu vực đọc PDF -->
        <div id="pdf-container" class="flex-1 overflow-auto p-6 flex justify-center items-start bg-zinc-950">
            <!-- Khung bao phủ chứa Canvas và Lớp TextLayer -->
            <div id="page-wrapper" class="pdf-page-container">
                <canvas id="pdf-render"></canvas>
                <div id="text-layer" class="textLayer"></div>
            </div>
        </div>

        <!-- Sidebar hiển thị bài giảng AI Gemini -->
        <aside id="ai-sidebar" class="w-[480px] bg-zinc-900 border-l border-zinc-800 flex flex-col h-full hidden shrink-0 z-10">
            <div class="p-4 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/80">
                <h2 class="font-bold text-emerald-400 flex items-center space-x-2">
                    <span>💡 Bài giảng & Giải thích Y Khoa</span>
                </h2>
                <button id="close-sidebar" class="text-zinc-400 hover:text-white text-xl px-2">&times;</button>
            </div>
            
            <div id="ai-content" class="flex-1 p-5 overflow-y-auto space-y-4 text-sm leading-relaxed text-zinc-300">
                <!-- Nội dung giải thích từ Gemini sẽ xuất hiện ở đây -->
            </div>
        </aside>
    </main>

    <!-- SCRIPT XỬ LÝ RENDER VÀ TƯƠNG TÁC -->
    <script>
        // Cấu hình đường dẫn Worker và Font chuẩn cho PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        
        const CMAP_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/cmaps/';
        const STANDARD_FONTS_URL = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/standard_fonts/';

        const streamUrl = '/pdf-stream';
        let pdfDoc = null,
            pageNum = 1,
            pageIsRendering = false,
            pageNumIsPending = null;

        // Render Trang PDF Sắc Nét 100%
        const renderPage = num => {
            pageIsRendering = true;

            pdfDoc.getPage(num).then(page => {
                // Tối ưu độ phân giải sắc nét cho màn hình High-DPI / 2K
                const pixelRatio = window.devicePixelRatio || 1; 
                const baseScale = 1.6; // Scale kích thước hiển thị
                const viewport = page.getViewport({ scale: baseScale });

                const canvas = document.getElementById('pdf-render');
                const ctx = canvas.getContext('2d', { alpha: false });

                // Nhân bản Pixel thực tế để nét chữ căng đanh, không mờ
                canvas.height = Math.floor(viewport.height * pixelRatio);
                canvas.width = Math.floor(viewport.width * pixelRatio);

                // Ép kích thước hiển thị CSS vừa vặn
                canvas.style.width = Math.floor(viewport.width) + "px";
                canvas.style.height = Math.floor(viewport.height) + "px";

                const pageWrapper = document.getElementById('page-wrapper');
                pageWrapper.style.width = Math.floor(viewport.width) + "px";
                pageWrapper.style.height = Math.floor(viewport.height) + "px";

                // Tắt thuật toán mờ ảnh giúp chữ sắc nét tuyệt đối
                ctx.imageSmoothingEnabled = false;

                const renderContext = {
                    canvasContext: ctx,
                    transform: [pixelRatio, 0, 0, pixelRatio, 0, 0],
                    viewport: viewport
                };

                // 1. Render Nét Chữ Chuẩn Gốc Lên Canvas
                const renderTask = page.render(renderContext);

                renderTask.promise.then(() => {
                    pageIsRendering = false;
                    if (pageNumIsPending !== null) {
                        renderPage(pageNumIsPending);
                        pageNumIsPending = null;
                    }

                    // 2. Lấy Văn Bản Để Dựng Lớp TextLayer (Cho Phép Bôi Đen/Copy)
                    return page.getTextContent();
                }).then(textContent => {
                    const textLayerDiv = document.getElementById('text-layer');
                    textLayerDiv.innerHTML = '';

                    pdfjsLib.renderTextLayer({
                        textContentSource: textContent,
                        container: textLayerDiv,
                        viewport: viewport,
                        textDivs: []
                    });
                });

                document.getElementById('page-num').value = num;
            });
        };

        const queueRenderPage = num => {
            if (pageIsRendering) {
                pageNumIsPending = num;
            } else {
                renderPage(num);
            }
        };

        // Điều khiển chuyển trang
        document.getElementById('prev-page').addEventListener('click', () => {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        });

        document.getElementById('next-page').addEventListener('click', () => {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        });

        document.getElementById('page-num').addEventListener('change', (e) => {
            const val = parseInt(e.target.value);
            if(val > 0 && val <= pdfDoc.numPages) {
                pageNum = val;
                queueRenderPage(pageNum);
            }
        });

        // Nạp File PDF với Font Đầy Đủ
        pdfjsLib.getDocument({
            url: streamUrl,
            cMapUrl: CMAP_URL,
            cMapPacked: true,
            standardFontDataUrl: STANDARD_FONTS_URL
        }).promise.then(pdfDoc_ => {
            pdfDoc = pdfDoc_;
            document.getElementById('page-count').textContent = pdfDoc.numPages;
            renderPage(pageNum);
        }).catch(err => {
            console.error("Lỗi khi nạp file PDF:", err);
            alert("Không thể nạp file PDF. Vui lòng kiểm tra lại đường dẫn file trên server.");
        });

        // --- XỬ LÝ GỌI API GEMINI GIẢI THÍCH ---
        const btnExplain = document.getElementById('btn-explain');
        const aiSidebar = document.getElementById('ai-sidebar');
        const aiContent = document.getElementById('ai-content');
        const closeSidebar = document.getElementById('close-sidebar');

        closeSidebar.addEventListener('click', () => aiSidebar.classList.add('hidden'));

        btnExplain.addEventListener('click', async () => {
            aiSidebar.classList.remove('hidden');
            aiContent.innerHTML = `
                <div class="flex items-center justify-center py-16 space-x-3 text-emerald-400">
                    <svg class="animate-spin h-6 w-6 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="font-medium">Đang trích xuất chữ & Đang gọi Gemini...</span>
                </div>
            `;

            try {
                // Trích xuất toàn bộ văn bản của trang hiện tại
                const page = await pdfDoc.getPage(pageNum);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');

                // Gọi Route API trong Laravel
                const response = await fetch('/api/explain-page', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        page: pageNum,
                        text: pageText
                    })
                });

                const data = await response.json();

                // Hiển thị bài giảng trả về từ AI
                aiContent.innerHTML = `
                    <div class="p-3 bg-zinc-800 rounded-lg border border-zinc-700 mb-4">
                        <h3 class="font-bold text-emerald-400">📌 Bài giảng Trang ${pageNum}</h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Phân tích bởi Gemini API</p>
                    </div>
                    <div class="text-zinc-300 leading-relaxed text-sm space-y-3">
                        ${data.explanation.replace(/\n/g, '<br>')}
                    </div>
                `;

            } catch (error) {
                aiContent.innerHTML = `<p class="text-red-400">Đã xảy ra lỗi khi giải thích: ${error.message}</p>`;
            }
        });
    </script>
</body>
</html>