

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
PAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_pages") # Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
JSONS_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_jsons") # Thư mục lưu file raw_json output
IMAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_images") # Thư mục lưu file ảnh thật trích ra từ PDF (đã convert sang RGB để hiển thị web)




doc = fitz.open(pdf_path)
page = doc[0]  # mỗi file chỉ có đúng 1 trang
# ///////////////////////// Lưu file PDF dưới dạng svg
# Lưu toàn bộ file PDF dưới dạng svg
svg_code = page.get_svg_image()
with open("svg_full.svg", "w", encoding="utf-8") as f:
    f.write(svg_code)
print("Đã xuất file SVG Full!")
    
# Tạo redaction cho toàn bộ vùng text, chỉ xóa text, giữ nguyên ảnh + vector
page.add_redact_annot(page.rect)
page.apply_redactions(
    images=fitz.PDF_REDACT_IMAGE_NONE,        # giữ ảnh
    graphics=fitz.PDF_REDACT_LINE_ART_NONE,   # giữ đường vẽ/vector
    text=fitz.PDF_REDACT_TEXT_REMOVE,         # xóa text
)
svg = page.get_svg_image()  # text_as_path mặc định, nhưng giờ không còn text nào
with open("svg_without_text.svg", "w", encoding="utf-8") as f:
    f.write(svg)
    
print("Đã xuất file SVG không có text!")
# /////////////////////////
doc.close()