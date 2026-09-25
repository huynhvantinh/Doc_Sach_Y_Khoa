# #############################################################################################
# CÁCH SỬ DỤNG:
# Chạy file BƯỚC 1: để tìm ra những hình ảnh có img_hash xuất hiện nhiều lần, vì nếu một hình có nội dung giống nhau thì img_hash sẽ giống nhau (Đã được chứng minh ở file test_xref.py)
#                   vào thư mục đã lưu hình để lấy những img_hash chính là phần đầu trong tên hình, rồi dám img_hash này vào BƯỚC 2
# Chạy file BƯỚC 2: ở BƯỚC 1 ta biết được img_hash cần xóa thì sau đó tiến hành xóa những hình nào có img_hash giống vậy
#               Dùng file BUOC_2_Xoa_WaterMark_1.py thì sẽ xóa được WaterMark nhưng còn lại viền
#               Dùng file BUOC_2_Xoa_WaterMark_2.py thì sẽ xóa được WaterMark và xóa luôn khung viền => OK
#               Dùng file BUOC_2_Xoa_WaterMark_3.py thì sẽ xóa được WaterMark và xóa luôn khung viền giống file BUOC_2_Xoa_WaterMark_2 nhưng có vẻ hơi dư phần Form => OK, nhưng cần xem lại có cần thiết không
# #############################################################################################

# #############################################################################################
# BƯỚC 2 (v3 - KHÔNG DÙNG get_image_rects / get_xobjects vì PDF này co /Resources
# ke thua tu node cha, khien 2 ham do tra ve RONG dù get_images() van dung).
#
# CACH XU LY MOI: giu nguyen logic checksum (Buoc 1), nhung thay vi redact theo
# toa do (rect), ta xoa TRUC TIEP cum lenh "/<ten_resource> Do" trong noi dung
# content stream (text) cua trang - vi ten resource (vd 'QQAPIm825692ea') da
# duoc get_images() tra ve chinh xac, khong phu thuoc vao Resources tree.
# #############################################################################################


import hashlib
import os
import re
import fitz  # PyMuPDF

# --- CẤU HÌNH ---
# input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL_p1_20.pdf"
input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL.pdf"
output_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL___OK.pdf"
deleted_images_dir = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/thu_muc_hinh_da_xoa"

os.makedirs(deleted_images_dir, exist_ok=True)

target_checksums = [
    "0627217785258400fa81a32f982b2474",
    "db149becf6264561258b57c2e240dbd6",
    # Thêm các mã checksum khác vào đây...
]
checksums_to_delete = set(target_checksums)

doc = fitz.open(input_pdf)
print("🧹 BƯỚC 2 (v3): Xóa theo checksum bằng cách xóa lệnh vẽ trong content stream...\n")

total_deleted = 0
total_names_stripped = 0
xref_cache = {}

for page_num in range(len(doc)):
    page = doc[page_num]
    image_list = page.get_images()  # vẫn hoạt động đúng dù get_xobjects() rỗng

    target_names_this_page = set()  # tên resource cần xóa khỏi content stream trang này

    for img in image_list:
        xref = img[0]
        name = img[7] if len(img) > 7 else None  # tên resource, vd 'QQAPIm825692ea'

        if xref not in xref_cache:
            base_image = doc.extract_image(xref)
            image_bytes = base_image["image"]
            ext = base_image["ext"]
            img_hash_md5 = hashlib.md5(image_bytes).hexdigest()
            xref_cache[xref] = (img_hash_md5, image_bytes, ext)
        else:
            img_hash_md5, image_bytes, ext = xref_cache[xref]

        if img_hash_md5 in checksums_to_delete:
            # Lưu ảnh gốc để đối chứng (chỉ lưu 1 lần theo xref+trang)
            file_name = f"{img_hash_md5}___Page{page_num + 1}___ID{xref}.{ext}"
            save_path = os.path.join(deleted_images_dir, file_name)
            if not os.path.exists(save_path):
                with open(save_path, "wb") as f:
                    f.write(image_bytes)
            total_deleted += 1
            if name:
                target_names_this_page.add(name)

    if not target_names_this_page:
        continue

    # Xóa trực tiếp "/<ten> Do" trong tất cả content stream của trang này
    content_xrefs = page.get_contents()
    for cxref in content_xrefs:
        raw = doc.xref_stream(cxref)
        if raw is None:
            continue
        text = raw.decode("latin-1")

        modified = False
        for name in target_names_this_page:
            # escape ký tự đặc biệt trong tên (vd dấu # xuất hiện trong tên PDF name)
            pattern = re.compile(r"/" + re.escape(name) + r"\s+Do\b")
            new_text, n_sub = pattern.subn("", text)
            if n_sub > 0:
                print(f"  ❌ [Trang {page_num+1}] Xóa {n_sub} lượt lệnh vẽ '/{name} Do'")
                text = new_text
                modified = True
                total_names_stripped += n_sub

        if modified:
            doc.update_stream(cxref, text.encode("latin-1"))

    page.clean_contents()

doc.save(output_pdf, garbage=4, deflate=True)
doc.close()

print(f"\n✅ HOÀN TẤT BƯỚC 2!")
print(f"1. Tổng số lượt ảnh khớp checksum      : {total_deleted}")
print(f"2. Tổng số lệnh vẽ đã xóa khỏi stream  : {total_names_stripped}")
print(f"3. File PDF sạch đã lưu tại            : '{output_pdf}'")
print(f"4. Ảnh gốc lưu đối chứng tại           : '{deleted_images_dir}'")

if total_names_stripped == 0 and total_deleted > 0:
    print(
        "\n⚠️  Van co anh khop checksum nhung khong xoa duoc lenh ve nao. "
        "Co the ten resource (img[7]) khac voi ten thuc su dung trong content "
        "stream o mot so trang. Gui lai output nay de kiem tra tiep."
    )