<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đọc Sách Y Khoa - Auto Sync Page & AI Gemini</title>
    
    <!-- Tailwind CSS qua CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- PDF.js Library (Dùng ở trang mẹ để trích xuất text khi bấm nút AI) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        body { 
            background-color: #09090b; 
            color: #f4f4f5; 
            font-family: system-ui, -apple-system, sans-serif; 
        }

        /* Custom scrollbar cho Sidebar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #18181b; }
        ::-webkit-scrollbar-thumb { background: #3f3f46; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #52525b; }
    </style>
</head>
<body class="h-screen w-screen flex flex-col overflow-hidden">

    <!-- 1. HEADER / NAVBAR -->
    <header class="h-14 bg-zinc-900 border-b border-zinc-800 flex items-center justify-between px-6 shrink-0 z-10">
        <div class="flex items-center space-x-3">
            <span class="text-xl">📖</span>
            <h1 class="font-bold text-lg text-emerald-400">Y Khoa Smart Reader</h1>
            <span class="text-xs px-2 py-0.5 rounded bg-zinc-800 text-zinc-400 border border-zinc-700">Auto-Sync Page</span>
        </div>

        <!-- Thanh điều khiển trang & AI -->
        <div class="flex items-center space-x-4">
            <!-- Ô hiển thị / nhập số trang (Tự động nhảy khi cuộn PDF) -->
            <div class="flex items-center space-x-2 bg-zinc-800/80 px-3 py-1.5 rounded-lg border border-zinc-700">
                <span class="text-xs text-zinc-400 uppercase font-semibold">Trang hiện tại:</span>
                <input 
                    type="number" 
                    id="ai-page-num" 
                    value="1" 
                    min="1" 
                    class="w-16 text-center bg-zinc-900 border border-zinc-700 rounded text-emerald-400 font-bold py-0.5 text-sm focus:outline-none focus:border-emerald-500"
                >
                <span id="total-pages-label" class="text-xs text-zinc-500">/ --</span>
            </div>

            <!-- Nút gọi AI phân tích trang hiện tại -->
            <button 
                id="btn-explain" 
                class="bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white px-4 py-1.5 rounded-lg font-medium text-sm transition-all flex items-center space-x-2 shadow-lg shadow-emerald-950"
            >
                <span>✨ Phân Tích Trang Này</span>
            </button>
        </div>
    </header>

    <!-- 2. NỘI DUNG CHÍNH (PDF & SIDEBAR AI) -->
    <main class="flex-1 flex overflow-hidden">
        
        <!-- Cột Trái: Trình đọc PDF.js WebViewer nhúng trong iframe -->
        <div class="flex-1 h-full bg-zinc-950 relative">
            <!-- 
                LƯU Ý VỀ URL IFRAME:
                - Sử dụng đường dẫn viewer.html của PDF.js 
                - Tham số file: trỏ tới route stream PDF của Laravel (/pdf-stream)
            -->
            <iframe 
                id="pdf-frame" 
                src="https://mozilla.github.io/pdf.js/web/viewer.html?file=/pdf-stream" 
                class="w-full h-full border-none"
                allowfullscreen
            ></iframe>
        </div>

        <!-- Cột Phải: Sidebar Bài Giảng / Dịch Thuật Gemini AI -->
        <aside id="ai-sidebar" class="w-[450px] bg-zinc-900 border-l border-zinc-800 flex flex-col h-full hidden shrink-0 z-10">
            <div class="p-4 border-b border-zinc-800 flex justify-between items-center bg-zinc-900/90">
                <div class="flex items-center space-x-2">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    <h2 class="font-bold text-emerald-400 text-sm uppercase tracking-wider">Bài Giảng Y Khoa AI</h2>
                </div>
                <button id="close-sidebar" class="text-zinc-400 hover:text-white text-xl px-2">&times;</button>
            </div>
            
            <div id="ai-content" class="flex-1 p-5 overflow-y-auto space-y-4 text-sm leading-relaxed text-zinc-300">
                <!-- Nội dung giải thích từ Gemini sẽ nạp vào đây -->
            </div>
        </aside>
    </main>

    <!-- 3. JAVASCRIPT XỬ LÝ ĐỒNG BỘ VÀ GỌI GEMINI -->
    <script>
        // Cấu hình PDF.js Worker ở trang mẹ để hỗ trợ lấy text ngầm
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const pdfStreamUrl = '/pdf-stream';
        let pdfDoc = null;
        let currentPageNum = 1;

        // Elements
        const inputPageNum = document.getElementById('ai-page-num');
        const totalPagesLabel = document.getElementById('total-pages-label');
        const btnExplain = document.getElementById('btn-explain');
        const aiSidebar = document.getElementById('ai-sidebar');
        const aiContent = document.getElementById('ai-content');
        const closeSidebar = document.getElementById('close-sidebar');
        const pdfIframe = document.getElementById('pdf-frame');

        // 1. Nạp Document ngầm để chuẩn bị sẵn sàng lấy Text khi bấm nút AI
        pdfjsLib.getDocument(pdfStreamUrl).promise.then(doc => {
            pdfDoc = doc;
            totalPagesLabel.innerText = `/ ${doc.numPages}`;
        }).catch(err => console.error("Lỗi nạp PDF background:", err));

        // 2. LẮNG NGHE SỰ KIỆN TỪ IFRAME (Tự động nhảy số trang khi người dùng cuộn PDF)
        window.addEventListener('message', function(event) {
            // Nhận tin nhắn đổi trang gửi từ viewer bên trong iframe
            if (event.data && (event.data.type === 'PAGE_CHANGED' || event.data.pageNumber)) {
                const page = event.data.pageNumber || event.data.page;
                if (page && page !== currentPageNum) {
                    currentPageNum = page;
                    inputPageNum.value = page; // Đổi số trên ô input
                }
            }
        });

        // 3. Cho phép người dùng gõ số trang trực tiếp để chuyển trang trong PDF
        inputPageNum.addEventListener('change', (e) => {
            const page = parseInt(e.target.value);
            if (pdfDoc && page >= 1 && page <= pdfDoc.numPages) {
                currentPageNum = page;
                // Gửi lệnh điều khiển ngược lại cho iframe cuộn tới trang mong muốn
                pdfIframe.contentWindow.postMessage({ action: 'GOTO_PAGE', pageNumber: page }, '*');
            }
        });

        // 4. Bấm nút đóng Sidebar
        closeSidebar.addEventListener('click', () => aiSidebar.classList.add('hidden'));

        // 5. XỬ LÝ KHI BẤM NÚT "PHÂN TÍCH TRANG NÀY"
        btnExplain.addEventListener('click', async () => {
            const pageNum = parseInt(inputPageNum.value);

            if (!pdfDoc) {
                alert("Tài liệu đang được khởi tạo, vui lòng đợi vài giây...");
                return;
            }

            if (pageNum < 1 || pageNum > pdfDoc.numPages) {
                alert(`Số trang không hợp lệ (1 - ${pdfDoc.numPages}).`);
                return;
            }

            // Mở Sidebar & Hiện hiệu ứng Loading
            aiSidebar.classList.remove('hidden');
            aiContent.innerHTML = `
                <div class="flex flex-col items-center justify-center py-20 space-y-4 text-emerald-400">
                    <svg class="animate-spin h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="font-medium text-sm text-zinc-300">Đang trích xuất văn bản trang ${pageNum}...</span>
                </div>
            `;

            try {
                // A. Trích xuất Text của trang hiện tại bằng PDF.js
                const page = await pdfDoc.getPage(pageNum);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');

                if (!pageText.trim()) {
                    aiContent.innerHTML = `<p class="text-amber-400">Trang này là hình ảnh scan hoặc không có văn bản dạng Text.</p>`;
                    return;
                }

                aiContent.querySelector('span').innerText = `Gemini AI đang phân tích Y Khoa trang ${pageNum}...`;

                // B. Gửi Text sang Laravel Backend để gọi Gemini API
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

                // C. Renders bài giảng AI ra Sidebar
                aiContent.innerHTML = `
                    <div class="p-3 bg-zinc-800/80 rounded-lg border border-zinc-700/80 mb-4">
                        <h3 class="font-bold text-emerald-400 text-base">📌 Bài Giảng Trang ${pageNum}</h3>
                        <p class="text-xs text-zinc-400 mt-1">Trích xuất & Tổng hợp bởi Gemini AI</p>
                    </div>
                    <div class="text-zinc-300 leading-relaxed text-sm space-y-3 prose prose-invert">
                        ${data.explanation ? data.explanation.replace(/\n/g, '<br>') : 'Không nhận được phản hồi từ AI.'}
                    </div>
                `;

            } catch (error) {
                console.error("AI Error:", error);
                aiContent.innerHTML = `<p class="text-red-400 bg-red-950/30 p-3 rounded border border-red-800">Có lỗi xảy ra: ${error.message}</p>`;
            }
        });
    </script>
</body>
</html>