<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đọc Sách Y Khoa - Native Chrome Quality & AI</title>
    <!-- Tailwind CSS qua CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- PDF.js Library (Dùng ngầm để trích xuất text gửi AI, không vẽ Canvas) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        body { 
            background-color: #121212; 
            color: #e0e0e0; 
            font-family: system-ui, -apple-system, sans-serif; 
        }

        /* Styling thanh cuộn */
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
            <span class="text-sm text-zinc-400">| Gray's Anatomy (Native Chrome Quality)</span>
        </div>

        <!-- Ô nhập trang để gọi AI -->
        <div class="flex items-center space-x-3 bg-zinc-800 px-4 py-1.5 rounded-lg border border-zinc-700">
            <span class="text-sm text-zinc-300">Trang muốn giải thích:</span>
            <input type="number" id="ai-page-num" value="1" min="1" class="w-16 text-center bg-zinc-900 border border-zinc-700 rounded text-white py-0.5 text-sm font-medium">
        </div>

        <!-- Nút tương tác AI -->
        <div class="flex items-center space-x-3">
            <button id="btn-explain" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-1.5 rounded-lg font-medium text-sm transition flex items-center space-x-2">
                <span>✨ Phân Tích & Dịch Trang</span>
            </button>
        </div>
    </header>

    <!-- 2. NỘI DUNG CHÍNH (LAYOUT 2 CỘT TỐI ƯU MÀN HÌNH 2K) -->
    <main class="flex-1 flex overflow-hidden">
        
        <!-- Cột Trái: Trình Xem PDF Chuẩn Chrome Native (Sắc nét 100%, nét đanh, tương phản tuyệt đối) -->
        <div class="flex-1 h-full bg-zinc-950 relative">
            <iframe 
                id="pdf-frame" 
                src="/pdf-stream#toolbar=1&view=FitH" 
                class="w-full h-full border-none">
            </iframe>
        </div>

        <!-- Cột Phải: Sidebar Bài Giảng AI Gemini (Nằm cạnh file PDF trên màn hình 2K) -->
        <aside id="ai-sidebar" class="w-[480px] bg-zinc-900 border-l border-zinc-800 flex flex-col h-full hidden shrink-0 z-10">
            <div class="p-4 border-b border-zinc-800 flex justify-between items-center bg-zinc-900">
                <h2 class="font-bold text-emerald-400 flex items-center space-x-2">
                    <span>💡 Bài giảng & Giải thích Y Khoa</span>
                </h2>
                <button id="close-sidebar" class="text-zinc-400 hover:text-white text-xl px-2">&times;</button>
            </div>
            
            <div id="ai-content" class="flex-1 p-5 overflow-y-auto space-y-4 text-sm leading-relaxed text-zinc-300">
                <!-- Bài giảng trả về từ Gemini -->
            </div>
        </aside>
    </main>

    <!-- SCRIPT XỬ LÝ TRÍCH XUẤT TEXT VÀ GỌI GEMINI API -->
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        const streamUrl = '/pdf-stream';
        let pdfDoc = null;

        // Load ngầm PDF Document bằng PDF.js để sẵn sàng lấy Text khi cần
        pdfjsLib.getDocument(streamUrl).promise.then(doc => {
            pdfDoc = doc;
            console.log("PDF Document loaded in background for AI extraction. Total pages:", doc.numPages);
        }).catch(err => {
            console.error("Lỗi nạp PDF ở background:", err);
        });

        // Tương tác Sidebar AI
        const btnExplain = document.getElementById('btn-explain');
        const aiSidebar = document.getElementById('ai-sidebar');
        const aiContent = document.getElementById('ai-content');
        const closeSidebar = document.getElementById('close-sidebar');

        closeSidebar.addEventListener('click', () => aiSidebar.classList.add('hidden'));

        btnExplain.addEventListener('click', async () => {
            const pageNum = parseInt(document.getElementById('ai-page-num').value);

            if (!pdfDoc) {
                alert("Hệ thống đang chuẩn bị tài liệu, vui lòng thử lại sau vài giây.");
                return;
            }

            if (pageNum < 1 || pageNum > pdfDoc.numPages) {
                alert(`Số trang không hợp lệ. Cuốn sách này có từ trang 1 đến ${pdfDoc.numPages}.`);
                return;
            }

            // Mở Sidebar & Hiện Loading
            aiSidebar.classList.remove('hidden');
            aiContent.innerHTML = `
                <div class="flex items-center justify-center py-16 space-x-3 text-emerald-400">
                    <svg class="animate-spin h-6 w-6 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span class="font-medium">Đang đọc trang ${pageNum} & Gọi Gemini API...</span>
                </div>
            `;

            try {
                // 1. Trích xuất Text ngầm từ trang PDF được chọn
                const page = await pdfDoc.getPage(pageNum);
                const textContent = await page.getTextContent();
                const pageText = textContent.items.map(item => item.str).join(' ');

                // 2. Gửi Text sang Controller Laravel
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

                // 3. Hiển thị bài giảng
                aiContent.innerHTML = `
                    <div class="p-3 bg-zinc-800 rounded-lg border border-zinc-700 mb-4">
                        <h3 class="font-bold text-emerald-400">📌 Bài Giảng Trang ${pageNum}</h3>
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