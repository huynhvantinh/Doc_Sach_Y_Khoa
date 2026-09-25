# Code này dùng để kiểm tra thủ công rằng các hình có xref thì có img_hash giống nhau

import hashlib
import os
import fitz  # PyMuPDF

# --- CẤU HÌNH ---
input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_Test.pdf"
output_dir = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/thu_muc_kiem_tra_xref"

# Khai báo khoảng trang cần kiểm tra (Ví dụ: Từ trang 1 đến trang 20)
start_page = 1  # Trang bắt đầu (tính từ 1)
end_page = 20  # Trang kết thúc

# Tạo thư mục đầu ra
os.makedirs(output_dir, exist_ok=True)

doc = fitz.open(input_pdf)
total_pages = len(doc)

# Đảm bảo khoảng trang không vượt quá số trang thực tế của PDF
start_idx = max(0, start_page - 1)
end_idx = min(total_pages, end_page)

print(
    f"🔍 Đang kiểm tra và trích xuất hình ảnh từ Trang {start_idx + 1} đến Trang {end_idx}..."
)

extracted_count = 0

for page_num in range(start_idx, end_idx):
    page = doc[page_num]
    image_list = page.get_images()

    print(f"\n📍 Trang {page_num + 1}: Tìm thấy {len(image_list)} đối tượng ảnh")

    for img in image_list:
        xref = img[0]

        # Trích xuất dữ liệu ảnh nhị phân để tính MD5
        base_image = doc.extract_image(xref)
        image_bytes = base_image["image"]
        ext = base_image["ext"]

        img_hash_md5 = hashlib.md5(image_bytes).hexdigest()

        # Đặt tên file theo chuẩn: Page[number]___[img_hash_md5]___ID[xref].[ext]
        file_name = f"Page{page_num + 1}___[{img_hash_md5}]___ID[{xref}].{ext}"
        save_path = os.path.join(output_dir, file_name)

        # Lưu ảnh ra thư mục
        with open(save_path, "wb") as f:
            f.write(image_bytes)

        extracted_count += 1
        print(f"  📸 Đã xuất: {file_name}")

doc.close()

print(
    f"\n✅ HOÀN TẤT! Đã xuất tổng cộng {extracted_count} tệp ảnh ra thư mục:"
)
print(f"📂 '{output_dir}'")