# #############################################################################################
# CÁCH SỬ DỤNG:
# Chạy file BƯỚC 1: để tìm ra những hình ảnh có img_hash xuất hiện nhiều lần, vì nếu một hình có nội dung giống nhau thì img_hash sẽ giống nhau (Đã được chứng minh ở file test_xref.py)
#                   vào thư mục đã lưu hình để lấy những img_hash chính là phần đầu trong tên hình, rồi dám img_hash này vào BƯỚC 2
# Chạy file BƯỚC 2: ở BƯỚC 1 ta biết được img_hash cần xóa thì sau đó tiến hành xóa những hình nào có img_hash giống vậy
#               Dùng file BUOC_2_Xoa_WaterMark_1.py thì sẽ xóa được WaterMark nhưng còn lại viền
#               Dùng file BUOC_2_Xoa_WaterMark_2.py thì sẽ xóa được WaterMark và xóa luôn khung viền => OK
#               Dùng file BUOC_2_Xoa_WaterMark_3.py thì sẽ xóa được WaterMark và xóa luôn khung viền giống file BUOC_2_Xoa_WaterMark_2 nhưng có vẻ hơi dư phần Form => OK, nhưng cần xem lại có cần thiết không
# #############################################################################################


import hashlib
import os
import fitz  # PyMuPDF

# --- CẤU HÌNH ---
# input_pdf = "Gray_Anatomy.pdf"
# output_pdf = "Gray_Anatomy_clean.pdf"
# deleted_images_dir = "thu_muc_anh_da_xoa"  # Thư mục lưu các hình đã xóa

input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy.pdf" ##D:\00000_Y_SI_DA_KHOA\SACH_PDF_FOR_WEB_DOC_SACH
output_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_CLEANED.pdf"
deleted_images_dir = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/thu_muc_hinh_da_xoa"

# Tạo thư mục chứa ảnh bị xóa nếu chưa có
os.makedirs(deleted_images_dir, exist_ok=True)

# 💥 ĐIỀN CÁC MÃ CHECKSUM CẦN XÓA VÀO DANH SÁCH DƯỚI ĐÂY
# (Copy chuỗi 32 ký tự đầu tên file từ thư mục xuất ra ở Bước 1)
target_checksums = [
    "0627217785258400fa81a32f982b2474",
    "db149becf6264561258b57c2e240dbd6",
    # Thêm các mã checksum khác vào đây...
]

# Chuyển về dạng tập hợp (set) để tra cứu cực nhanh
checksums_to_delete = set(target_checksums)

doc = fitz.open(input_pdf)
print(f"🧹 BƯỚC 2: Bắt đầu xóa và sao lưu các hình ảnh có Checksum chỉ định...")

total_deleted = 0
xref_cache = {}  # Bộ nhớ đệm lưu MD5 và dữ liệu ảnh theo xref để tối ưu tốc độ

for page_num in range(len(doc)):
    page = doc[page_num]
    image_list = page.get_images()

    # Dùng danh sách tạm để lưu các xref cần xóa trong trang này
    xrefs_to_delete_on_page = []

    for img in image_list:
        xref = img[0]

        # Kiểm tra cache trước để tránh extract dữ liệu lặp lại - giảm tối đa thao tác đọc dữ liệu từ ổ đĩa
        if xref not in xref_cache:
            base_image = doc.extract_image(xref)
            image_bytes = base_image["image"]
            ext = base_image["ext"]
            img_hash_md5 = hashlib.md5(image_bytes).hexdigest()
            xref_cache[xref] = (img_hash_md5, image_bytes, ext)
        else:
            img_hash_md5, image_bytes, ext = xref_cache[xref]

        # Nếu Checksum của hình thuộc danh sách cần xóa
        if img_hash_md5 in checksums_to_delete:
            xrefs_to_delete_on_page.append((xref, img_hash_md5, image_bytes, ext))

    # Tiến hành lưu ảnh ra thư mục đối chứng và xóa khỏi trang
    for xref, img_hash_md5, image_bytes, ext in xrefs_to_delete_on_page:
        # 1. Đặt tên file theo chuẩn: [img_hash_md5]___Page[trang]___ID[xref].[ext]
        file_name = f"{img_hash_md5}___Page{page_num + 1}___ID{xref}.{ext}"
        save_path = os.path.join(deleted_images_dir, file_name)

        # 2. Ghi hình ảnh ra thư mục
        with open(save_path, "wb") as f:
            f.write(image_bytes)

        # 3. Xóa trực tiếp Image Object khỏi trang PDF
        page.delete_image(xref)

        total_deleted += 1
        print(f"  ❌ Đã xóa & lưu: {file_name}")

# Lưu thành file PDF mới hoàn tất
doc.save(output_pdf)
doc.close()

print(f"\n✅ HOÀN TẤT BƯỚC 2!")
print(f"1. Tổng số lượt hình ảnh đã bị xóa: {total_deleted}")
print(f"2. File PDF sạch đã lưu tại: '{output_pdf}'")
print(f"3. Tất cả hình ảnh bị xóa đã được lưu lại tại: '{deleted_images_dir}'")