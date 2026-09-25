# #############################################################################################
# KIEM TRA: cac anh co checksum chi dinh co DUNG CHUNG 1 object (xref) giua cac trang
# hay moi trang la 1 object rieng biet.
# #############################################################################################

import hashlib
import fitz  # PyMuPDF

input_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL_p1_20.pdf"

target_checksums = [
    "0627217785258400fa81a32f982b2474",
    "db149becf6264561258b57c2e240dbd6",
]
checksums_to_check = set(target_checksums)

doc = fitz.open(input_pdf)
xref_cache = {}

# checksum -> list of (page_num, xref, name)
occurrences = {c: [] for c in checksums_to_check}

for page_num in range(len(doc)):
    page = doc[page_num]
    for img in page.get_images():
        xref = img[0]
        name = img[7] if len(img) > 7 else None

        if xref not in xref_cache:
            base_image = doc.extract_image(xref)
            img_hash_md5 = hashlib.md5(base_image["image"]).hexdigest()
            xref_cache[xref] = img_hash_md5
        else:
            img_hash_md5 = xref_cache[xref]

        if img_hash_md5 in checksums_to_check:
            occurrences[img_hash_md5].append((page_num + 1, xref, name))

doc.close()

print("=" * 80)
for checksum, items in occurrences.items():
    print(f"\nChecksum: {checksum}")
    print(f"Tổng số lượt xuất hiện: {len(items)}")

    if not items:
        print("  (Không tìm thấy ảnh nào có checksum này trong file)")
        continue

    unique_xrefs = sorted(set(xref for _, xref, _ in items))
    print(f"Số object (xref) khác nhau: {len(unique_xrefs)}  -> xref: {unique_xrefs}")

    if len(unique_xrefs) == 1:
        print("=> KẾT LUẬN: TẤT CẢ các trang DÙNG CHUNG đúng 1 object (cùng xref).")
    else:
        print("=> KẾT LUẬN: Mỗi trang (hoặc nhóm trang) có OBJECT RIÊNG (xref khác nhau).")

    print("\nChi tiết từng lượt (trang - xref - tên resource):")
    for page_num, xref, name in items:
        print(f"  Trang {page_num:3d}  xref={xref:5d}  tên='{name}'")

print("\n" + "=" * 80)