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
# BƯỚC 2 (v4): Xóa ca trong Content Stream cua trang LAN trong Form XObject dung chung
#
# Ly do can ban v3 chi xoa duoc 8/70: watermark tren da so trang khong nam truc tiep
# trong content stream cua trang, ma nam trong 1 Form XObject DUNG CHUNG (dinh nghia
# 1 lan, moi trang chi goi "/Fm0 Do" de tai su dung). page.get_contents() KHONG lay
# duoc noi dung ben trong Form nay.
#
# CACH XU LY: 
#   1. Van quet tung trang de xac dinh checksum + gom TOAN BO ten resource can xoa
#      (gom chung 1 danh sach cho ca file, vi Form co the dung chung nhieu trang).
#   2. Xoa "/<ten> Do" trong content stream CUA TUNG TRANG (nhu v3).
#   3. QUET THEM: duyet TOAN BO object trong file, tim nhung object co Subtype = /Form
#      (day chinh la cac "khuon" dung chung), xoa "/<ten> Do" ben trong cac Form do.
# #############################################################################################


import hashlib
import os
import re
import fitz  # PyMuPDF

# --- CẤU HÌNH ---
input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL_p1_20.pdf"
output_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL___222.pdf"
deleted_images_dir = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/thu_muc_hinh_da_xoa"

os.makedirs(deleted_images_dir, exist_ok=True)

target_checksums = [
    "0627217785258400fa81a32f982b2474",
    "db149becf6264561258b57c2e240dbd6",
    # Thêm các mã checksum khác vào đây...
]
checksums_to_delete = set(target_checksums)

doc = fitz.open(input_pdf)
print("🧹 BƯỚC 2 (v4): Xóa theo checksum, quét cả trang lẫn Form XObject dùng chung...\n")

total_matched = 0          # so luot ANH KHOP checksum (chi la phat hien, chua phai da xoa)
total_stripped_page = 0    # so lenh Do da xoa trong content stream cua TRANG
total_stripped_form = 0    # so lenh Do da xoa trong Form XObject dung chung
xref_cache = {}
global_target_names = set()  # gom TAT CA ten resource can xoa, dung chung ca file

# ---------- GIAI DOAN 1: quet tung trang de xac dinh checksum + xoa ngay trong content stream cua trang ----------
for page_num in range(len(doc)):
    page = doc[page_num]
    image_list = page.get_images()

    target_names_this_page = set()

    for img in image_list:
        xref = img[0]
        name = img[7] if len(img) > 7 else None

        if xref not in xref_cache:
            base_image = doc.extract_image(xref)
            image_bytes = base_image["image"]
            ext = base_image["ext"]
            img_hash_md5 = hashlib.md5(image_bytes).hexdigest()
            xref_cache[xref] = (img_hash_md5, image_bytes, ext)
        else:
            img_hash_md5, image_bytes, ext = xref_cache[xref]

        if img_hash_md5 in checksums_to_delete:
            file_name = f"{img_hash_md5}___Page{page_num + 1}___ID{xref}.{ext}"
            save_path = os.path.join(deleted_images_dir, file_name)
            if not os.path.exists(save_path):
                with open(save_path, "wb") as f:
                    f.write(image_bytes)
            total_matched += 1
            if name:
                target_names_this_page.add(name)
                global_target_names.add(name)

    if not target_names_this_page:
        continue

    content_xrefs = page.get_contents()
    for cxref in content_xrefs:
        raw = doc.xref_stream(cxref)
        if raw is None:
            continue
        text = raw.decode("latin-1")

        modified = False
        for name in target_names_this_page:
            pattern = re.compile(r"/" + re.escape(name) + r"\s+Do\b")
            new_text, n_sub = pattern.subn("", text)
            if n_sub > 0:
                print(f"  ❌ [Trang {page_num+1} - content] Xóa {n_sub} lượt '/{name} Do'")
                text = new_text
                modified = True
                total_stripped_page += n_sub

        if modified:
            doc.update_stream(cxref, text.encode("latin-1"))

    page.clean_contents()

print(f"\n(Đã gom được {len(global_target_names)} tên resource cần xóa trên toàn file)\n")

# ---------- GIAI DOAN 2: quet TOAN BO object trong file, tim Form XObject, xoa ben trong do ----------
print("🔎 Đang quét toàn bộ object trong file để tìm Form XObject dùng chung...\n")

forms_checked = 0
for xref in range(1, doc.xref_length()):
    try:
        subtype = doc.xref_get_key(xref, "Subtype")
    except Exception:
        continue

    # subtype tra ve dang tuple ('name', '/Form') neu la Form XObject
    if not subtype or subtype[1] != "/Form":
        continue

    forms_checked += 1
    raw = doc.xref_stream(xref)
    if raw is None:
        continue
    text = raw.decode("latin-1")

    modified = False
    for name in global_target_names:
        pattern = re.compile(r"/" + re.escape(name) + r"\s+Do\b")
        new_text, n_sub = pattern.subn("", text)
        if n_sub > 0:
            print(f"  ❌ [Form XObject xref={xref}] Xóa {n_sub} lượt '/{name} Do'")
            text = new_text
            modified = True
            total_stripped_form += n_sub

    if modified:
        doc.update_stream(xref, text.encode("latin-1"))

doc.save(output_pdf, garbage=4, deflate=True)
doc.close()

print("\n" + "=" * 70)
print(f"Số Form XObject đã kiểm tra          : {forms_checked}")
print(f"Số lượt ảnh khớp checksum (phát hiện) : {total_matched}")
print(f"Số lệnh vẽ xóa trong content stream    : {total_stripped_page}")
print(f"Số lệnh vẽ xóa trong Form XObject       : {total_stripped_form}")
print(f"TỔNG số lệnh vẽ đã xóa thật sự          : {total_stripped_page + total_stripped_form}")
print(f"File PDF sạch                          : {output_pdf}")
print("=" * 70)