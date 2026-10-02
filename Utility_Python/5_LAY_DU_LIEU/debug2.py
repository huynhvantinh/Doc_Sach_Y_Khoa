import fitz
import hashlib

doc = fitz.open("page-0001.pdf")
page = doc[0]

# Gọi cả 2 trường hợp để so sánh
info_no_hash = page.get_image_info(xrefs=True, hashes=False)
info_with_hash = page.get_image_info(xrefs=True, hashes=True)

for i, info in enumerate(info_with_hash):
    digest = info.get("digest")
    if digest is None:
        print(f"Ảnh #{i}: không có digest")
        continue

    print(f"--- Ảnh #{i} ---")
    print(f"Kiểu dữ liệu: {type(digest)}")
    print(f"Độ dài (bytes): {len(digest)}")
    print(f"Dạng hex: {digest.hex()}")

    # Thử lưu thẳng digest thành file ảnh - để chứng minh nó KHÔNG PHẢI ảnh
    fake_path = f"digest_test_{i}.png"
    with open(fake_path, "wb") as f:
        f.write(digest)
    print(f"Đã ghi {len(digest)} bytes ra {fake_path} (thử mở file này - sẽ báo lỗi 'không phải ảnh hợp lệ')")

    # So sánh: tự tính MD5 của ảnh THẬT trích bằng extract_image, xem có khớp với digest không
    xref = info.get("xref")
    if xref:
        base_image = doc.extract_image(xref)
        real_image_bytes = base_image["image"]
        real_md5 = hashlib.md5(real_image_bytes).digest()
        print(f"MD5 tự tính từ ảnh thật:  {real_md5.hex()}")
        print(f"Digest PyMuPDF trả về:    {digest.hex()}")
        print(f"Có khớp nhau không? {real_md5 == digest}")