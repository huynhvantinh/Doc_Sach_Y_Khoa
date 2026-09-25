# #############################################################################################
# CHAN DOAN (KHONG XOA GI CA): voi moi trang, phan biet:
#   - Anh "THUC SU VE" (co lenh "/<ten> Do" trong content stream cua chinh trang do)
#   - Anh "CHI KHAI BAO" (get_images() bao co, nhung khong tim thay lenh Do thuc te
#     trong content stream trang nay -> do dung chung /Resources voi trang khac)
#
# Cach dung: doi input_pdf, chay, doc bang liet ke.
# #############################################################################################

import hashlib
import re
import fitz  # PyMuPDF

# input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL_p1_20.pdf"
input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL___222.pdf"

target_checksums = [
    "0627217785258400fa81a32f982b2474",
    "db149becf6264561258b57c2e240dbd6",
    # Dan cac checksum can kiem tra vao day...
]
checksums_to_check = set(target_checksums)

doc = fitz.open(input_pdf)
xref_cache = {}

print(f"{'Trang':<6}{'Tên resource':<22}{'Trạng thái'}")
print("-" * 70)

pages_with_real_draw = set()
pages_declared_only = set()

for page_num in range(len(doc)):
    page = doc[page_num]
    image_list = page.get_images()

    # gom noi dung TAT CA content stream cua trang nay thanh 1 chuoi de tim kiem
    full_text = ""
    for cxref in page.get_contents():
        raw = doc.xref_stream(cxref)
        if raw:
            full_text += raw.decode("latin-1")

    for img in image_list:
        xref = img[0]
        name = img[7] if len(img) > 7 else None
        if not name:
            continue

        if xref not in xref_cache:
            base_image = doc.extract_image(xref)
            img_hash_md5 = hashlib.md5(base_image["image"]).hexdigest()
            xref_cache[xref] = img_hash_md5
        else:
            img_hash_md5 = xref_cache[xref]

        if img_hash_md5 not in checksums_to_check:
            continue

        pattern = re.compile(r"/" + re.escape(name) + r"\s+Do\b")
        has_real_draw = bool(pattern.search(full_text))

        if has_real_draw:
            status = "✅ CÓ VẼ THẬT (Do tồn tại trong content stream)"
            pages_with_real_draw.add(page_num + 1)
        else:
            status = "⚪ chỉ khai báo trong Resources (không có Do ở trang này)"
            pages_declared_only.add(page_num + 1)

        print(f"{page_num+1:<6}{name:<22}{status}")

doc.close()

print("\n" + "=" * 70)
print(f"Số trang có ít nhất 1 lệnh Do thật sự : {len(pages_with_real_draw)}")
print(f"  -> Danh sách trang: {sorted(pages_with_real_draw)}")
print(f"Số trang chỉ 'khai báo' (dùng Resources chung, không có Do): "
      f"{len(pages_declared_only - pages_with_real_draw)}")
print("=" * 70)