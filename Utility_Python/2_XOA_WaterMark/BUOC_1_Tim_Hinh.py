# #############################################################################################
# CÁCH SỬ DỤNG:
# Chạy file BƯỚC 1: để tìm ra những hình ảnh có img_hash xuất hiện nhiều lần, vì nếu một hình có nội dung giống nhau thì img_hash sẽ giống nhau (Đã được chứng minh ở file test_xref.py)
#                   vào thư mục đã lưu hình để lấy những img_hash chính là phần đầu trong tên hình, rồi dám img_hash này vào BƯỚC 2
# Chạy file BƯỚC 2: ở BƯỚC 1 ta biết được img_hash cần xóa thì sau đó tiến hành xóa những hình nào có img_hash giống vậy
# #############################################################################################



# =================================================================================
# ================================================================================= CÁCH 1: OK - KHÔNG dùng Cache vẫn OK, nếu muốn dùng cache để nhanh hơn thì dùng cách 2
# =================================================================================

# from collections import defaultdict
# import hashlib
# import os
# import fitz  # PyMuPDF

# # --- CẤU HÌNH ---
# # input_pdf = "Gray_Anatomy.pdf"  # Đường dẫn tới file PDF của bạn
# # output_dir = "thu_muc_kiem_tra_anh_2"  # Thư mục chứa các ảnh trùng lặp
# input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/Python_For_Guyton/Gray_Anatomy.pdf"
# output_dir = "/mnt/d/00000_Y_SI_DA_KHOA/Python_For_Guyton/thu_muc_kiem_tra_anh"

# os.makedirs(output_dir, exist_ok=True)
# doc = fitz.open(input_pdf)

# print("🔍 BƯỚC 1: Đang quét toàn bộ file PDF và tính toán Checksum MD5...")

# # Cấu trúc: image_tracker[md5] = {"bytes": ..., "ext": ..., "count": 0}
# image_tracker = defaultdict(lambda: {"bytes": None, "ext": None, "count": 0})

# for page in doc:
#     for img in page.get_images():
#         xref = img[0]
#         base_image = doc.extract_image(xref)
#         image_bytes = base_image["image"]
#         ext = base_image["ext"]

#         # Tính checksum MD5 cho dữ liệu ảnh gốc
#         img_hash_md5 = hashlib.md5(image_bytes).hexdigest()

#         if image_tracker[img_hash_md5]["bytes"] is None:
#             image_tracker[img_hash_md5]["bytes"] = image_bytes
#             image_tracker[img_hash_md5]["ext"] = ext

#         image_tracker[img_hash_md5]["count"] += 1

# doc.close()

# # Lọc các hình lặp lại >= 2 lần trên toàn bộ file
# duplicate_images = {
#     img_hash_md5: data
#     for img_hash_md5, data in image_tracker.items()
#     if data["count"] >= 2
# }

# print(f"👉 Tìm thấy {len(duplicate_images)} mẫu hình ảnh lặp lại từ 2 lần trở lên.")
# print("📸 Đang trích xuất ảnh ra thư mục...")

# for img_hash_md5, data in duplicate_images.items():
#     count = data["count"]
#     ext = data["ext"]

#     # Đặt tên file theo chuẩn: [checksum]___([số lần xuất hiện] lần).[ext]
#     file_name = f"{img_hash_md5}___({count} lần).{ext}"
#     save_path = os.path.join(output_dir, file_name)

#     with open(save_path, "wb") as f:
#         f.write(data["bytes"])

#     print(f"  + Đã lưu: {file_name}")

# print(f"\n✅ HOÀN TẤT BƯỚC 1! Hãy mở thư mục '{output_dir}' để kiểm tra mắt thường.")
# print("👉 Copy chuỗi Checksum (32 ký tự đầu tên file) của các logo cần xóa để dán vào Bước 2.")




# =================================================================================
# ================================================================================= CÁCH 2: OK - Dùng Cache thì nhanh hơn nữa - giảm tối đa thao tác đọc dữ liệu từ ổ đĩa
# =================================================================================
# Dùng bộ nhớ đệm (cache) cho các xref đã gặp, vì trong PDF các xref giống nhau thì dữ liệu ảnh là hoàn toàn như nhau
from collections import defaultdict
import hashlib
import os
import fitz  # PyMuPDF

# --- CẤU HÌNH ---
# input_pdf = "Gray_Anatomy.pdf"
# output_dir = "thu_muc_kiem_tra_anh"
input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy.pdf" #D:\00000_Y_SI_DA_KHOA\SACH_PDF_FOR_WEB_DOC_SACH
output_dir = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/thu_muc_hinh"

os.makedirs(output_dir, exist_ok=True)
doc = fitz.open(input_pdf)

print("🔍 BƯỚC 1: Đang quét toàn bộ file PDF và tính toán Checksum MD5...")

image_tracker = defaultdict(lambda: {"bytes": None, "ext": None, "count": 0})
xref_to_md5 = {}  # Bộ nhớ đệm lưu MD5 cho từng xref để tránh extract nhiều lần

for page in doc:
    for img in page.get_images():
        xref = img[0]

        # Nếu xref này chưa được tính MD5 thì mới extract dữ liệu ảnh - giảm tối đa thao tác đọc dữ liệu từ ổ đĩa
        if xref not in xref_to_md5:
            base_image = doc.extract_image(xref)
            image_bytes = base_image["image"]
            ext = base_image["ext"]

            img_hash_md5 = hashlib.md5(image_bytes).hexdigest()

            # Lưu vào cache
            xref_to_md5[xref] = (img_hash_md5, image_bytes, ext)
        else:
            img_hash_md5, image_bytes, ext = xref_to_md5[xref]

        # Cập nhật tracker
        if image_tracker[img_hash_md5]["bytes"] is None:
            image_tracker[img_hash_md5]["bytes"] = image_bytes
            image_tracker[img_hash_md5]["ext"] = ext

        image_tracker[img_hash_md5]["count"] += 1

doc.close()

# Lọc các hình lặp lại >= 2 lần trên toàn bộ file
duplicate_images = {
    img_hash_md5: data
    for img_hash_md5, data in image_tracker.items()
    if data["count"] >= 2
}

print(f"👉 Tìm thấy {len(duplicate_images)} mẫu hình ảnh lặp lại từ 2 lần trở lên.")
print("📸 Đang trích xuất ảnh ra thư mục...")

for img_hash_md5, data in duplicate_images.items():
    count = data["count"]
    ext = data["ext"]

    file_name = f"{img_hash_md5}___({count} lần).{ext}"
    save_path = os.path.join(output_dir, file_name)

    with open(save_path, "wb") as f:
        f.write(data["bytes"])

    print(f"  + Đã lưu: {file_name}")

print(f"\n✅ HOÀN TẤT BƯỚC 1! Hãy mở thư mục '{output_dir}' để kiểm tra mắt thường.")
print("👉 Copy chuỗi Checksum (32 ký tự đầu tên file) của các logo cần xóa để dán vào Bước 2.")