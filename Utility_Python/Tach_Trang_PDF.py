import os
import fitz  # PyMuPDF

# =========================================================================
# ⚙️ CẤU HÌNH THÔNG SỐ (Số trang xem trực tiếp trên ứng dụng đọc PDF)
# =========================================================================

# Thư mục gốc chứa sách
BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH"

# File PDF nguồn
INPUT_PDF_PATH = os.path.join(BASE_DIR, "12_Histology_7th.pdf")

# Thư mục đích chứa các file đã tách
OUTPUT_DIR = os.path.join(BASE_DIR, "12_Histology_7th_pages")

# Trang bắt đầu và kết thúc (1-based index)
START_PAGE = 21  # Trang bắt đầu
END_PAGE   = 21  # Trang kết thúc

# =========================================================================
# 🚀 TIẾN HÀNH TRÍCH XUẤT ĐỘNG
# =========================================================================

# 1. Kiểm tra file PDF nguồn có tồn tại không
if not os.path.exists(INPUT_PDF_PATH):
    print(f"❌ Lỗi: Không tìm thấy file nguồn tại: {INPUT_PDF_PATH}")
    exit()

# 2. Tạo thư mục chứa file tách ra nếu chưa có
os.makedirs(OUTPUT_DIR, exist_ok=True)

# 3. Tạo tên file đầu ra dựa trên tên file gốc và phạm vi trang
filename_without_ext, ext = os.path.splitext(os.path.basename(INPUT_PDF_PATH))
if START_PAGE == END_PAGE:
    output_filename = f"{filename_without_ext}_p{START_PAGE:04d}{ext}"
else:
    output_filename = f"{filename_without_ext}_p{START_PAGE:04d}-{END_PAGE:04d}{ext}"

OUTPUT_PDF_PATH = os.path.join(OUTPUT_DIR, output_filename)

# 4. Mở PDF và kiểm tra giới hạn trang
doc_src = fitz.open(INPUT_PDF_PATH)
total_pages = len(doc_src)

if START_PAGE < 1 or END_PAGE > total_pages or START_PAGE > END_PAGE:
    print(f"❌ Lỗi: Trang hợp lệ từ 1 đến {total_pages}. Bạn chọn: {START_PAGE} -> {END_PAGE}")
    doc_src.close()
    exit()

# Quy đổi sang 0-based index để làm việc với PyMuPDF
from_page_idx = START_PAGE - 1
to_page_idx = END_PAGE - 1

# 5. Tạo file PDF mới và chèn phạm vi trang đã chọn
doc_dst = fitz.open()

print(f"-> Đang trích xuất từ trang {START_PAGE} đến trang {END_PAGE}...")
doc_dst.insert_pdf(doc_src, from_page=from_page_idx, to_page=to_page_idx)

# 6. Lưu file và dọn dẹp dung lượng
doc_dst.save(OUTPUT_PDF_PATH, deflate=True, garbage=4)

doc_src.close()
doc_dst.close()

print(f"🎉 HOÀN TẤT! File mới đã lưu tại: {OUTPUT_PDF_PATH}")