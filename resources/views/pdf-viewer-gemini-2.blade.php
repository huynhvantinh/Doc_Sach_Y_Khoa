<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đọc Sách Y Khoa & AI AI Assistant</title>
    <!-- Tailwind CSS qua CDN để dựng UI nhanh, sạch -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- PDF.js Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        /* Tối ưu hiển thị cho màn hình 2K */
        body { background-color: #121212; color: #e0e0e0; font-family: sans-serif; }
        #pdf-render { display: block; margin: 0 auto; max-width: 100%; height: auto; }
        /* Style cho thanh cuộn */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 4px; }
    </style>

    <!-- Bổ sung CSS cho TextLayer trong <head> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf_viewer.min.css">
    <style>
        .pdf-page-container {
            position: relative;
            margin: 0 auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        /* Lớp text phủ lên canvas để bôi đen/copy */
        .textLayer {
            opacity: 0.2; /* Mờ nhẹ hoặc chọn màu chuẩn */
            mix-blend-mode: multiply;
        }
    </style>
</head>
<body class="h-screen w-screen flex flex-col overflow-hidden">

    <!-- 1. HEADER / TOOLBAR -->
    <header class="h-14 bg-zinc-900 border-b border-zinc-800 flex items-center justify-between px-6 shrink-0">
        <div class="flex items-center space-x-4">
            <h1 class="font-semibold text-lg text-emerald-400">📖 Y Khoa Reader</h1>
            <span class="text-sm text-zinc-400">| Gray's Anatomy</span>
        </div>

        <!-- Điều khiển Trang -->
        <div class="flex items-center space-x-3 bg-zinc-800 px-4 py-1.5 rounded-lg border border-zinc-700">
            <button id="prev-page" class="hover:bg-zinc-700 px-2.5 py-1 rounded text-sm transition">◄ Trước</button>
            <span class="text-sm">Trang <input type="number" id="page-num" value="1" class="w-12 text-center bg-zinc-900 border border-zinc-700 rounded text-white py-0.5"> / <span id="page-count">--</span></span>
            <button id="next-page" class="hover:bg-zinc-700 px-2.5 py-1 rounded text-sm transition">Sau ►</button>
        </div>

        <!-- Nút tương tác AI -->
        <div class="flex items-center space-x-3">
            <button id="btn-explain" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-lg font-medium text-sm transition flex items-center space-x-2">
                <span>✨ Giải thích & Dịch trang này</span>
            </button>
        </div>
    </header>

    <!-- 2. NỘI DUNG CHÍNH (KHU VỰC ĐỌC PDF & SIDEBAR) -->
    <main class="flex-1 flex overflow-hidden">
        <!-- Area 1: Khung hiển thị PDF (Chiếm phần lớn màn hình) -->
        {{-- <div id="pdf-container" class="flex-1 overflow-auto p-4 flex justify-center items-start bg-zinc-950">
            <canvas id="pdf-render" class="shadow-2xl border border-zinc-800 rounded"></canvas>
        </div> --}}
        
        <div id="pdf-container" class="flex-1 overflow-auto p-4 flex justify-center items-start bg-zinc-950">
            <!-- Khung chứa cả Canvas lẫn TextLayer -->
            <div id="page-wrapper" class="pdf-page-container">
                <canvas id="pdf-render"></canvas>
                <div id="text-layer" class="textLayer"></div>
            </div>
        </div>

        <!-- Area 2: Sidebar hiển thị bài giảng/giải thích (Có thể đóng/mở) -->
        <aside id="ai-sidebar" class="w-[450px] bg-zinc-900 border-l border-zinc-800 flex flex-col h-full hidden">
            <div class="p-4 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/50">
                <h2 class="font-bold text-emerald-400 flex items-center space-x-2">
                    <span>💡 Bài giảng & Dịch thuật</span>
                </h2>
                <button id="close-sidebar" class="text-zinc-400 hover:text-white text-lg px-2">&times;</button>
            </div>
            
            <!-- Nội dung bài giải thích -->
            <div id="ai-content" class="flex-1 p-5 overflow-y-auto space-y-4 text-sm leading-relaxed text-zinc-300">
                <!-- Nội dung Gemini trả về sẽ render vào đây -->
            </div>
        </aside>
    </main>

    <!-- SCRIPT XỬ LÝ PDF.JS VÀ TƯƠNG TÁC -->
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const url = '/pdf-stream';
        let pdfDoc = null,
            pageNum = 1,
            pageIsRendering = false,
            pageNumIsPending = null;

        const canvas = document.getElementById('pdf-render'),
              ctx = canvas.getContext('2d');

        // Render trang PDF
        const renderPage = num => {
            pageIsRendering = true;

            pdfDoc.getPage(num).then(page => {
                // Tối ưu zoom cho màn hình 2K (viewport scale = 1.8 hoặc 2.0)
                const viewport = page.getViewport({ scale: 1.8 });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderCtx = {
                    canvasContext: ctx,
                    viewport
                };

                page.render(renderCtx).promise.then(() => {
                    pageIsRendering = false;
                    if (pageNumIsPending !== null) {
                        renderPage(pageNumIsPending);
                        pageNumIsPending = null;
                    }
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

        // Chuyển trang
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

        // Tải PDF
        pdfjsLib.getDocument(url).promise.then(pdfDoc_ => {
            pdfDoc = pdfDoc_;
            document.getElementById('page-count').textContent = pdfDoc.numPages;
            renderPage(pageNum);
        });

        // --- XỬ LÝ TƯƠNG TÁC GỌI API GEMINI ---
        const btnExplain = document.getElementById('btn-explain');
        const aiSidebar = document.getElementById('ai-sidebar');
        const aiContent = document.getElementById('ai-content');
        const closeSidebar = document.getElementById('close-sidebar');

        closeSidebar.addEventListener('click', () => aiSidebar.classList.add('hidden'));

        btnExplain.addEventListener('click', async () => {
            // 1. Mở Sidebar & Hiện Loading
            aiSidebar.classList.remove('hidden');
            aiContent.innerHTML = `
                <div class="flex items-center justify-center py-12 space-x-3 text-emerald-400">
                    <svg class="animate-spin h-5 w-5 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>Đang đọc trang ${pageNum} & Đang phân tích...</span>
                </div>
            `;

            try {
                // 2. Trích xuất text từ trang PDF hiện tại bằng PDF.js
                const page = await pdfDoc.getPage(pageNum);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');

                // 3. Gọi Route API trong Laravel
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

                // 4. Hiển thị kết quả trả về từ Gemini
                aiContent.innerHTML = `
                    <div class="p-3 bg-zinc-800/80 rounded-lg border border-zinc-700">
                        <h3 class="font-bold text-emerald-400 mb-1">📌 Tóm tắt & Giải thích Trang ${pageNum}</h3>
                        <p class="text-xs text-zinc-400">Tạo bởi Gemini API</p>
                    </div>
                    <div class="prose prose-invert max-w-none text-zinc-300">
                        ${data.explanation.replace(/\n/g, '<br>')}
                    </div>
                `;

            } catch (error) {
                aiContent.innerHTML = `<p class="text-red-400">Có lỗi xảy ra khi gọi API giải thích: ${error.message}</p>`;
            }
        });
    </script>
</body>
</html>