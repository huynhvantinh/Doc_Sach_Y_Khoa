import os
import fitz  # PyMuPDF

# =========================================================================
# ⚙️ CẤU HÌNH THÔNG SỐ (Số trang xem trực tiếp trên ứng dụng đọc PDF)
# =========================================================================

# INPUT_PDF = "Gray_Anatomy.pdf"
# INPUT_PDF = "Gray_DangKhoa.pdf"
# INPUT_PDF = "Grays_Anatomy_CLEANED.pdf"
# INPUT_PDF = "Grays_Anatomy_NO_URL.pdf"
INPUT_PDF   = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_TEST.pdf"


# START_PAGE = 278  # Trang bắt đầu (1-based)
# END_PAGE   = 279    # Trang kết thúc (1-based)
START_PAGE = 293   # Trang bắt đầu (1-based)
END_PAGE   = 293   # Trang kết thúc (1-based)

# =========================================================================
# 🚀 TIẾN HÀNH TRÍCH XUẤT ĐỘNG
# =========================================================================

# Tự động tạo tên file đầu ra dựa trên tên file gốc và phạm vi trang
filename_without_ext, ext = os.path.splitext(INPUT_PDF)
if START_PAGE == END_PAGE:
    OUTPUT_PDF = f"{filename_without_ext}_p{START_PAGE}{ext}"
else:
    OUTPUT_PDF = f"{filename_without_ext}_p{START_PAGE}-{END_PAGE}{ext}"

# Quy đổi sang 0-based index để làm việc với PyMuPDF
from_page_idx = START_PAGE - 1
to_page_idx = END_PAGE - 1

doc_src = fitz.open(INPUT_PDF)
doc_dst = fitz.open()

print(f"-> Đang trích xuất từ trang {START_PAGE} đến trang {END_PAGE}...")

# Sao chép dải trang đã chọn sang file mới
doc_dst.insert_pdf(doc_src, from_page=from_page_idx, to_page=to_page_idx)

# Lưu file và tối ưu dung lượng
doc_dst.save(OUTPUT_PDF, deflate=True, garbage=4)

doc_src.close()
doc_dst.close()

print(f"🎉 HOÀN TẤT! File mới đã lưu tại: {OUTPUT_PDF}")