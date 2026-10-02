import os
import fitz

# Sách 1
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/1_Grays_Anatomy_Students.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/1_Grays_Anatomy_Students_pages"

# Sách 2
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/2_Grays_Anatomy_Clinical_Practice.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/2_Grays_Anatomy_Clinical_Practice_pages"

# Sách 3
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/3_Moores_Clinically_Anatomy.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/3_Moores_Clinically_Anatomy_pages"

# Sách 4
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/4_Junqueiras_Histology.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/4_Junqueiras_Histology_pages"

# Sách 5 - KHÔNG OK - vì SVG vẫn còn dính text
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/5_Histology.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/5_Histology_pages"

# Sách 6
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/6_Histology_Abraham.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/6_Histology_Abraham_pages"

# Sách 7
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/7_Guyton.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/7_Guyton_pages"

# Sách 8 - KHÔNG OK - vì SVG vẫn còn dính text
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/8_Pathophysiology.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/8_Pathophysiology_pages"

# Sách 9
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/9_Robbins_Cotran_Pathologic.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/9_Robbins_Cotran_Pathologic_pages"

# Sách 10 - KHÔNG OK - vì SVG vẫn còn dính text
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/10_Histology_7th.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/10_Histology_7th_pages"

# Sách 11 - OK - Trong danh sách các sách Histoloy A Text And Atlas thì sách 11 và 12 là OK vì lưu SVG không có text được
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/11_Histology_6th.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/11_Histology_6th_pages"

# Sách 12 - OK - Trong danh sách các sách Histoloy A Text And Atlas thì sách 11 và 12 là OK vì lưu SVG không có text được
INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/12_Histology_7th.pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/12_Histology_7th_pages"

os.makedirs(OUTPUT_DIR, exist_ok=True)

doc_src = fitz.open(INPUT_PDF)
total_pages = len(doc_src)

for i in range(total_pages):
    doc_dst = fitz.open()
    doc_dst.insert_pdf(doc_src, from_page=i, to_page=i)
    
    out_path = os.path.join(OUTPUT_DIR, f"page-{i+1:04d}.pdf")
    doc_dst.save(out_path, deflate=True, garbage=4)
    doc_dst.close()
    
    if (i + 1) % 50 == 0:
        print(f"Đã xử lý {i+1}/{total_pages} trang...")

doc_src.close()
print(f"🎉 Hoàn tất! {total_pages} trang đã lưu tại: {OUTPUT_DIR}")