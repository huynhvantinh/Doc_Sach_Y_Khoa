<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf_viewer.min.css">

<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body {
    width: 100%; height: 100%;
    background: #3a3c3f;
    overflow: hidden;
    font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
  }

  /* ---------- Toolbar ---------- */
  #toolbar {
    position: fixed; top: 0; left: 0; right: 0; height: 40px;
    background: #222; color: #ddd; display: flex; align-items: center;
    gap: 10px; padding: 0 12px; font-size: 13px; z-index: 30;
    user-select: none;
  }
  #toolbar .title {
    font-weight: 600; color: #fff; margin-right: 6px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 240px;
  }
  #toolbar button {
    background: #3a3a3a; border: 1px solid #555; color: #ddd;
    padding: 3px 10px; border-radius: 4px; cursor: pointer; font-size: 12px;
  }
  #toolbar button:hover { background: #4a4a4a; }
  #toolbar input[type=number] {
    width: 50px; padding: 2px 4px; text-align: center;
    background: #1a1a1a; color: #fff; border: 1px solid #555; border-radius: 3px;
  }
  #toolbar .sep { width: 1px; height: 20px; background: #555; }
  #toolbar .spacer { flex: 1; }
  #loading-indicator {
    color: #9ca3af; font-size: 12px; display: none;
  }

  /* ---------- Viewer area ---------- */
  #viewer {
    position: absolute; top: 40px; left: 0; right: 0; bottom: 0;
    overflow: auto;
    display: flex; justify-content: center;
    background: #525659;
    padding: 20px 0 60px 0;
  }
  #page-wrapper {
    position: relative;
    margin: 0 auto;
  }
  #pdf-canvas {
    display: block;
    box-shadow: 0 0 10px rgba(0,0,0,.55);
    background: #fff;
  }

  /* Text layer chính thức của PDF.js: trong suốt, đè khớp lên canvas để select/copy chữ */
  .textLayer {
    position: absolute;
    left: 0; top: 0; right: 0; bottom: 0;
    overflow: hidden;
    line-height: 1;
    opacity: 1;
  }
  .textLayer span {
    color: transparent;
    position: absolute;
    white-space: pre;
    cursor: text;
    transform-origin: 0% 0%;
  }
  .textLayer ::selection {
    background: rgba(37, 99, 235, 0.35);
  }

  /* ---------- Nút hành động nổi bên phải ---------- */
  #action-buttons {
    position: fixed; right: 14px; top: 50px; z-index: 25;
    display: flex; flex-direction: column; gap: 8px;
  }
  .action-btn {
    background: #2563eb; color: #fff; border: none; padding: 9px 12px;
    border-radius: 6px; cursor: pointer; font-size: 12.5px; text-align: left;
    box-shadow: 0 2px 6px rgba(0,0,0,.4);
    white-space: nowrap;
  }
  .action-btn:hover { background: #1d4ed8; }
  .action-btn:disabled { background: #555; cursor: wait; }

  /* ---------- Modal kết quả ---------- */
  #modal-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6);
    z-index: 50; align-items: center; justify-content: center;
  }
  #modal-box {
    background: #fff; width: min(760px, 90vw); max-height: 82vh;
    border-radius: 8px; display: flex; flex-direction: column; overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,.4);
  }
  #modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 18px; background: #f3f4f6; border-bottom: 1px solid #e5e7eb;
  }
  #modal-header h3 { font-size: 14.5px; color: #111827; }
  #modal-header button {
    border: none; background: none; font-size: 20px; cursor: pointer; color: #6b7280;
    line-height: 1;
  }
  #modal-header button:hover { color: #111827; }
  #modal-body {
    padding: 18px; overflow-y: auto; font-size: 14px; line-height: 1.7;
    white-space: pre-wrap; color: #1f2937;
  }
  #modal-footer {
    padding: 10px 18px; border-top: 1px solid #e5e7eb; background: #f9fafb;
    display: flex; justify-content: flex-end; gap: 8px;
  }
  #modal-footer button {
    font-size: 12.5px; padding: 6px 12px; border-radius: 5px; cursor: pointer;
    border: 1px solid #d1d5db; background: #fff; color: #374151;
  }
  #modal-footer button:hover { background: #f3f4f6; }
</style>
</head>
<body>

<div id="toolbar">
  <span class="title">{{ $title }}</span>
  <div class="sep"></div>
  <button id="prevPage">‹ Trước</button>
  <span>Trang <input type="number" id="pageNum" min="1" value="1"> / <span id="pageCount">-</span></span>
  <button id="nextPage">Sau ›</button>
  <div class="sep"></div>
  <button id="zoomOut">−</button>
  <span id="zoomLevel">100%</span>
  <button id="zoomIn">+</button>
  <button id="fitWidth">Vừa ngang</button>
  <div class="spacer"></div>
  <span id="loading-indicator">Đang tải trang...</span>
</div>

<div id="viewer">
  <div id="page-wrapper">
    <canvas id="pdf-canvas"></canvas>
    <div id="text-layer" class="textLayer"></div>
  </div>
</div>

<div id="action-buttons">
  <button class="action-btn" id="btnTranslate">🌐 Dịch trang này</button>
  <button class="action-btn" id="btnLecture">📘 Tạo bài giảng</button>
</div>

<div id="modal-overlay">
  <div id="modal-box">
    <div id="modal-header">
      <h3 id="modal-title">Kết quả</h3>
      <button onclick="closeModal()">✕</button>
    </div>
    <div id="modal-body">Đang xử lý...</div>
    <div id="modal-footer">
      <button id="btnCopyResult">Sao chép</button>
      <button onclick="closeModal()">Đóng</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const PDF_URL = @json($pdfUrl);
const CSRF = document.querySelector('meta[name=csrf-token]').content;
const TRANSLATE_URL = @json(route('api.pdf.translate'));
const LECTURE_URL   = @json(route('api.pdf.lecture'));

let pdfDoc = null;
let pageNum = 1;
let scale = 1.5;
let fitWidthMode = true;
let renderTask = null; // để hủy khi render chồng

const canvas = document.getElementById('pdf-canvas');
const ctx = canvas.getContext('2d');
const textLayerDiv = document.getElementById('text-layer');
const viewer = document.getElementById('viewer');
const wrapper = document.getElementById('page-wrapper');
const loadingIndicator = document.getElementById('loading-indicator');

// Độ phân giải điểm ảnh thật của màn hình (2K/4K thường là 1.25 - 2)
const outputScale = window.devicePixelRatio || 1;

pdfjsLib.getDocument(PDF_URL).promise.then(doc => {
  pdfDoc = doc;
  document.getElementById('pageCount').textContent = doc.numPages;
  renderPage(pageNum);
}).catch(err => {
  document.getElementById('loading-indicator').style.display = 'inline';
  loadingIndicator.textContent = 'Lỗi tải PDF: ' + err.message;
});

async function renderPage(num) {
  loadingIndicator.style.display = 'inline';
  loadingIndicator.textContent = 'Đang tải trang...';

  const page = await pdfDoc.getPage(num);

  let useScale = scale;
  if (fitWidthMode) {
    const unscaled = page.getViewport({ scale: 1 });
    useScale = (viewer.clientWidth - 40) / unscaled.width;
  }
  const viewport = page.getViewport({ scale: useScale });

  // --- Canvas render ở độ phân giải cao rồi thu nhỏ bằng CSS -> nét trên màn 2K/4K ---
  canvas.width = Math.floor(viewport.width * outputScale);
  canvas.height = Math.floor(viewport.height * outputScale);
  canvas.style.width = Math.floor(viewport.width) + 'px';
  canvas.style.height = Math.floor(viewport.height) + 'px';

  wrapper.style.width = Math.floor(viewport.width) + 'px';
  wrapper.style.height = Math.floor(viewport.height) + 'px';

  const transform = outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null;

  if (renderTask) {
    try { renderTask.cancel(); } catch (e) {}
  }
  renderTask = page.render({ canvasContext: ctx, viewport, transform });
  await renderTask.promise;

  // --- Text layer: cho phép select / copy chữ như PDF gốc ---
  textLayerDiv.innerHTML = '';
  textLayerDiv.style.width = Math.floor(viewport.width) + 'px';
  textLayerDiv.style.height = Math.floor(viewport.height) + 'px';

  const textContent = await page.getTextContent();
  pdfjsLib.renderTextLayer({
    textContentSource: textContent,
    container: textLayerDiv,
    viewport: viewport,
    textDivs: [],
  });

  document.getElementById('pageNum').value = num;
  document.getElementById('zoomLevel').textContent = Math.round((useScale / 1.5) * 100) + '%';
  loadingIndicator.style.display = 'none';
}

// Lấy toàn bộ text thô của 1 trang (dùng cho dịch / tạo bài giảng)
async function getPageText(num) {
  const page = await pdfDoc.getPage(num);
  const content = await page.getTextContent();
  return content.items.map(i => i.str).join(' ');
}

// ---------- Điều khiển toolbar ----------
document.getElementById('prevPage').onclick = () => {
  if (pageNum > 1) { pageNum--; renderPage(pageNum); }
};
document.getElementById('nextPage').onclick = () => {
  if (pageNum < pdfDoc.numPages) { pageNum++; renderPage(pageNum); }
};
document.getElementById('pageNum').onchange = e => {
  const n = parseInt(e.target.value, 10);
  if (pdfDoc && n >= 1 && n <= pdfDoc.numPages) { pageNum = n; renderPage(pageNum); }
};
document.getElementById('zoomIn').onclick = () => {
  fitWidthMode = false; scale = Math.min(4, scale + 0.2); renderPage(pageNum);
};
document.getElementById('zoomOut').onclick = () => {
  fitWidthMode = false; scale = Math.max(0.4, scale - 0.2); renderPage(pageNum);
};
document.getElementById('fitWidth').onclick = () => {
  fitWidthMode = true; renderPage(pageNum);
};
window.addEventListener('resize', () => { if (fitWidthMode && pdfDoc) renderPage(pageNum); });

// ---------- Gọi API Laravel ----------
async function callApi(url, text) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': CSRF,
    },
    body: JSON.stringify({ text }),
  });
  return res.json();
}

let lastResultText = '';

function openModal(title) {
  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-body').textContent = 'Đang xử lý, vui lòng đợi...';
  document.getElementById('modal-overlay').style.display = 'flex';
}
function closeModal() {
  document.getElementById('modal-overlay').style.display = 'none';
}
document.getElementById('modal-overlay').addEventListener('click', e => {
  if (e.target.id === 'modal-overlay') closeModal();
});
document.getElementById('btnCopyResult').onclick = () => {
  navigator.clipboard.writeText(lastResultText || '');
};

document.getElementById('btnTranslate').onclick = async () => {
  const btn = document.getElementById('btnTranslate');
  btn.disabled = true;
  openModal(`Bản dịch — Trang ${pageNum}`);
  try {
    const text = await getPageText(pageNum);
    const data = await callApi(TRANSLATE_URL, text);
    lastResultText = data.translated || data.error || 'Không có kết quả';
    document.getElementById('modal-body').textContent = lastResultText;
  } catch (err) {
    document.getElementById('modal-body').textContent = 'Lỗi: ' + err.message;
  } finally {
    btn.disabled = false;
  }
};

document.getElementById('btnLecture').onclick = async () => {
  const btn = document.getElementById('btnLecture');
  btn.disabled = true;
  openModal(`Bài giảng — Trang ${pageNum}`);
  try {
    const text = await getPageText(pageNum);
    const data = await callApi(LECTURE_URL, text);
    lastResultText = data.lecture || data.error || 'Không có kết quả';
    document.getElementById('modal-body').textContent = lastResultText;
  } catch (err) {
    document.getElementById('modal-body').textContent = 'Lỗi: ' + err.message;
  } finally {
    btn.disabled = false;
  }
};
</script>
</body>
</html>
