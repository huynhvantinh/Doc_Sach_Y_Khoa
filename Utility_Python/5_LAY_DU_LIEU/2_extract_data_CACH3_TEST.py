import os
import re
import json
import glob
import fitz  # PyMuPDF
import mysql.connector
from mysql.connector import Error as MySQLError
import xml.etree.ElementTree as ET #Để xuát file svg không text - KHÔNG DÙNG

# =========================================================================
# CONFIG - đổi các giá trị này khi làm sách khác
# =========================================================================

BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

BOOK_SLUG = "test-tong-hop"  # phải khớp với BOOK_SLUG đã tạo ở file 1
PAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_pages_2") # Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
JSONS_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_jsons") # Thư mục lưu file raw_json output
IMAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_images") # Thư mục lưu file ảnh thật trích ra từ PDF (đã convert sang RGB để hiển thị web)

# =========================================================================
# CHỌN FILE CỤ THỂ CẦN XỬ LÝ
# =========================================================================
TARGET_FILE = "page-0021.pdf"
pdf_path = os.path.join(PAGES_DIR, TARGET_FILE)

# Kiểm tra file có tồn tại hay không
if not os.path.exists(pdf_path):
    print(f"Lỗi: Không tìm thấy file tại đường dẫn: {pdf_path}")
else:
    print(f"Bắt đầu xử lý file: {pdf_path}")
    
    # Tạo sẵn các thư mục đầu ra nếu chưa có
    os.makedirs(JSONS_DIR, exist_ok=True)
    os.makedirs(IMAGES_DIR, exist_ok=True)

    # Mở file PDF
    doc = fitz.open(pdf_path)
    page = doc[0]  # mỗi file lẻ chỉ có đúng 1 trang

    # ///////////////////////// Lưu file PDF dưới dạng SVG
    
    # 1. Lưu toàn bộ file PDF dưới dạng SVG gốc (Có chứa Text)
    svg_code = page.get_svg_image()
    
    # Lưu file ra thư mục output (hoặc lưu cùng thư mục script)
    output_full_svg = os.path.join(JSONS_DIR, f"{os.path.splitext(TARGET_FILE)[0]}_full.svg")
    with open(output_full_svg, "w", encoding="utf-8") as f:
        f.write(svg_code)
    print(f"Đã xuất file SVG Full: {output_full_svg}")

    # 2. Tạo redaction cho toàn bộ vùng text: Chỉ xóa text, giữ nguyên ảnh + vector
    page.add_redact_annot(page.rect)
    page.apply_redactions(
        images=fitz.PDF_REDACT_IMAGE_NONE,        # giữ ảnh
        graphics=fitz.PDF_REDACT_LINE_ART_NONE,   # giữ đường vẽ/vector
        text=fitz.PDF_REDACT_TEXT_REMOVE,         # xóa text
    )

    # 3. Lấy SVG chỉ chứa nền vector + ảnh (Không có text)
    svg_no_text = page.get_svg_image()
    
    output_no_text_svg = os.path.join(JSONS_DIR, f"{os.path.splitext(TARGET_FILE)[0]}_no_text.svg")
    with open(output_no_text_svg, "w", encoding="utf-8") as f:
        f.write(svg_no_text)

    print(f"Đã xuất file SVG không có text: {output_no_text_svg}")
    
    # /////////////////////////
    doc.close()