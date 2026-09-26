import os
import fitz

INPUT_PDF = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive).pdf"
OUTPUT_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_pages"

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