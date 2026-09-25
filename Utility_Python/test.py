import fitz  # PyMuPDF

def visualize_pdf_objects(input_pdf_path, output_pdf_path):
    # 1. Mở file PDF gốc
    doc = fitz.open(input_pdf_path)
    
    # Quy định bảng màu (RGB từ 0.0 đến 1.0)
    COLOR_TEXT = (1.0, 0.0, 0.0)      # Đỏ cho Text Vector
    COLOR_IMAGE = (0.0, 0.4, 1.0)     # Xanh dương cho Hình ảnh (Raster Image)
    COLOR_DRAWING = (0.0, 0.8, 0.2)   # Xanh lá cho Đường vẽ Vector (Graphics)
    
    OPACITY = 0.35  # Độ trong suốt (35%)

    for page_num in range(len(doc)):
        page = doc[page_num]

        # -------------------------------------------------------------
        # A. NHẬN DIỆN HÌNH ẢNH (IMAGES) - Tô màu XANH DƯƠNG
        # -------------------------------------------------------------
        image_info_list = page.get_image_info(hashes=False)
        for img in image_info_list:
            bbox = img["bbox"]  # (x0, y0, x1, y1)
            rect = fitz.Rect(bbox)
            
            # Tạo hình chữ nhật tô màu lên trang
            annot = page.add_rect_annot(rect)
            annot.set_colors(stroke=COLOR_IMAGE, fill=COLOR_IMAGE)
            annot.set_opacity(OPACITY)
            annot.update()

        # -------------------------------------------------------------
        # B. NHẬN DIỆN ĐƯỜNG VẼ VECTOR (DRAWINGS/GRAPHICS) - Tô màu XANH LÁ
        # -------------------------------------------------------------
        drawings = page.get_drawings()
        for draw in drawings:
            rect = draw["rect"]  # Tọa độ bao quanh đường vẽ
            # Lọc bỏ các đường vẽ quá nhỏ hoặc bao trùm cả trang
            if rect.width > 2 and rect.height > 2 and rect != page.rect:
                annot = page.add_rect_annot(rect)
                annot.set_colors(stroke=COLOR_DRAWING, fill=COLOR_DRAWING)
                annot.set_opacity(OPACITY)
                annot.update()

        # -------------------------------------------------------------
        # C. NHẬN DIỆN VĂN BẢN (TEXT BLOCKS) - Tô màu ĐỎ
        # -------------------------------------------------------------
        # Sử dụng "blocks" để lấy từng khối văn bản
        text_blocks = page.get_text("blocks")
        for block in text_blocks:
            # block = (x0, y0, x1, y1, "text_content", block_no, block_type)
            if block[6] == 0:  # block_type == 0 nghĩa là Text
                rect = fitz.Rect(block[:4])
                annot = page.add_rect_annot(rect)
                annot.set_colors(stroke=COLOR_TEXT, fill=COLOR_TEXT)
                annot.set_opacity(OPACITY)
                annot.update()

    # 2. Lưu ra file PDF mới
    doc.save(output_pdf_path)
    doc.close()
    print(f"Đã xuất file kiểm tra tại: {output_pdf_path}")

# --- CHẠY THỬ NGHỆM ---
input_file = "/mnt/d/00000_Y_SI_DA_KHOA/Python_For_Guyton/Gray_Anatomy_p265.pdf"
output_file = "/mnt/d/00000_Y_SI_DA_KHOA/Python_For_Guyton/Gray_Anatomy_p265_visualized.pdf"

visualize_pdf_objects(input_file, output_file)