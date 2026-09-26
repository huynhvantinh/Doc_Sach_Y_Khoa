import json
import fitz  # PyMuPDF

def export_pdf_to_json(input_pdf_path, output_json_path):
    doc = fitz.open(input_pdf_path)
    pdf_data = []

    for page_num in range(len(doc)):
        page = doc[page_num]
        
        # 1. Lấy toàn bộ thông tin cấu trúc Text, Font, Bounding Box dạng Dict/JSON
        # "flags=fitz.TEXT_DEHYPHENATE" giúp nối các từ bị ngắt dòng bằng dấu gạch ngang
        page_dict = page.get_text("dict", flags=fitz.TEXT_DEHYPHENATE)
        
        # 2. Lấy thêm thông tin vị trí các Hình ảnh (Images) trên trang
        image_list = page.get_image_info(hashes=False)

        page_info = {
            "page_number": page_num + 1,
            "width": page.rect.width,
            "height": page.rect.height,
            "text_blocks": page_dict["blocks"], # Chứa chi tiết từng dòng, từng từ, font, bbox
            "images": image_list                # Chứa vị trí bbox của các hình ảnh
        }
        
        pdf_data.append(page_info)

    # 3. Ghi ra file JSON (định dạng đẹp với indent=2)
    with open(output_json_path, "w", encoding="utf-8") as f:
        json.dump(pdf_data, f, ensure_ascii=False, indent=2)

    print(f"Đã xuất toàn bộ dữ liệu ra file: {output_json_path}")

# --- CHẠY THỬ NGHỆM ---
input_pdf   = "/mnt/d/00000_Y_DA_KHOA/Python_For_Guyton/Gray_Anatomy_p266.pdf"
output_json = "/mnt/d/00000_Y_DA_KHOA/Python_For_Guyton/Gray_Anatomy_p266.json"
input_pdf   = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/page-0293.pdf"
output_json = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/page-0293.json"
input_pdf   = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/zpage-0294.pdf"
output_json = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/zpage-0294.json"
# input_pdf   = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_TEST_p293.pdf"
# output_json = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_TEST_p293.json"

export_pdf_to_json(input_pdf, output_json)