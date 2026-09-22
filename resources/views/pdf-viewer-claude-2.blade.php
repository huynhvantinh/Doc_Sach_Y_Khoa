<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title }}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  html, body { width:100%; height:100%; background:#3a3c3f; overflow:hidden; font-family:system-ui, sans-serif; }

  #toolbar {
    position:fixed; top:0; left:0; right:0; height:40px;
    background:#222; color:#ddd; display:flex; align-items:center;
    gap:10px; padding:0 12px; font-size:13px; z-index:20;
  }
  #toolbar button {
    background:#3a3a3a; border:1px solid #555; color:#ddd;
    padding:3px 8px; border-radius:4px; cursor:pointer; font-size:12px;
  }
  #toolbar button:hover { background:#4a4a4a; }
  #toolbar input[type=number] { width:50px; padding:2px 4px; }

  #viewer {
    position:absolute; top:40px; left:0; right:0; bottom:0;
    overflow:auto; display:flex; justify-content:center;
    background:#525659;
  }
  #pdf-canvas { box-shadow:0 0 8px rgba(0,0,0,.5); }

  #action-buttons {
    position:fixed; right:14px; top:50px; z-index:20;
    display:flex; flex-direction:column; gap:8px;
  }
  .action-btn {
    background:#2563eb; color:#fff; border:none; padding:8px 10px;
    border-radius:6px; cursor:pointer; font-size:12px; text-align:left;
    box-shadow:0 2px 6px rgba(0,0,0,.4);
  }
  .action-btn:hover { background:#1d4ed8; }
  .action-btn:disabled { background:#555; cursor:wait; }

  #modal-overlay {
    display:none; position:fixed; inset:0; background:rgba(0,0,0,.6);
    z-index:50; align-items:center; justify-content:center;
  }
  #modal-box {
    background:#fff; width:min(720px, 90vw); max-height:82vh;
    border-radius:8px; display:flex; flex-direction:column; overflow:hidden;
  }
  #modal-header {
    display:flex; justify-content:space-between; align-items:center;
    padding:10px 16px; background:#f3f4f6; border-bottom:1px solid #e5e7eb;
  }
  #modal-header h3 { font-size:14px; }
  #modal-header button { border:none; background:none; font-size:18px; cursor:pointer; }
  #modal-body { padding:16px; overflow-y:auto; font-size:14px; line-height:1.6; white-space:pre-wrap; }
</style>
</head>
<body>

<div id="toolbar">
  <button id="prevPage">‹ Trước</button>
  <span>Trang <input type="number" id="pageNum" min="1" value="1"> / <span id="pageCount">-</span></span>
  <button id="nextPage">Sau ›</button>
  <span style="margin-left:10px">|</span>
  <button id="zoomOut">−</button>
  <span id="zoomLevel">100%</span>
  <button id="zoomIn">+</button>
  <button id="fitWidth">Vừa ngang</button>
</div>

<div id="viewer"><canvas id="pdf-canvas"></canvas></div>

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
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

const PDF_URL = @json($pdfUrl);
const CSRF = document.querySelector('meta[name=csrf-token]').content;

let pdfDoc = null, pageNum = 1, scale = 1.5, fitWidthMode = true;
const canvas = document.getElementById('pdf-canvas');
const ctx = canvas.getContext('2d');
const viewer = document.getElementById('viewer');

pdfjsLib.getDocument(PDF_URL).promise.then(doc => {
  pdfDoc = doc;
  document.getElementById('pageCount').textContent = doc.numPages;
  renderPage(pageNum);
});

function renderPage(num) {
  pdfDoc.getPage(num).then(page => {
    let useScale = scale;
    if (fitWidthMode) {
      const unscaled = page.getViewport({ scale: 1 });
      useScale = (viewer.clientWidth - 40) / unscaled.width;
    }
    const viewport = page.getViewport({ scale: useScale });
    canvas.width = viewport.width;
    canvas.height = viewport.height;
    page.render({ canvasContext: ctx, viewport });
    document.getElementById('pageNum').value = num;
    document.getElementById('zoomLevel').textContent = Math.round(useScale / 1.5 * 100) + '%';
  });
}

// Lấy toàn bộ text của trang hiện tại (dùng cho dịch / bài giảng)
async function getPageText(num) {
  const page = await pdfDoc.getPage(num);
  const content = await page.getTextContent();
  return content.items.map(i => i.str).join(' ');
}

document.getElementById('prevPage').onclick = () => { if (pageNum > 1) { pageNum--; renderPage(pageNum); } };
document.getElementById('nextPage').onclick = () => { if (pageNum < pdfDoc.numPages) { pageNum++; renderPage(pageNum); } };
document.getElementById('pageNum').onchange = e => {
  const n = parseInt(e.target.value);
  if (n >= 1 && n <= pdfDoc.numPages) { pageNum = n; renderPage(pageNum); }
};
document.getElementById('zoomIn').onclick = () => { fitWidthMode = false; scale += 0.2; renderPage(pageNum); };
document.getElementById('zoomOut').onclick = () => { fitWidthMode = false; scale = Math.max(0.4, scale - 0.2); renderPage(pageNum); };
document.getElementById('fitWidth').onclick = () => { fitWidthMode = true; renderPage(pageNum); };

// ---- Gọi API ----
async function callApi(url, text) {
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
    body: JSON.stringify({ text }),
  });
  return res.json();
}

function openModal(title) {
  document.getElementById('modal-title').textContent = title;
  document.getElementById('modal-body').textContent = 'Đang xử lý, vui lòng đợi...';
  document.getElementById('modal-overlay').style.display = 'flex';
}
function closeModal() { document.getElementById('modal-overlay').style.display = 'none'; }

document.getElementById('btnTranslate').onclick = async () => {
  const btn = document.getElementById('btnTranslate');
  btn.disabled = true;
  openModal(`Bản dịch — Trang ${pageNum}`);
  const text = await getPageText(pageNum);
  const data = await callApi('{{ route("api.pdf.translate") }}', text);
  document.getElementById('modal-body').textContent = data.translated || data.error;
  btn.disabled = false;
};

document.getElementById('btnLecture').onclick = async () => {
  const btn = document.getElementById('btnLecture');
  btn.disabled = true;
  openModal(`Bài giảng — Trang ${pageNum}`);
  const text = await getPageText(pageNum);
  const data = await callApi('{{ route("api.pdf.lecture") }}', text);
  document.getElementById('modal-body').textContent = data.lecture || data.error;
  btn.disabled = false;
};

window.addEventListener('resize', () => { if (fitWidthMode) renderPage(pageNum); });
</script>
</body>
</html>