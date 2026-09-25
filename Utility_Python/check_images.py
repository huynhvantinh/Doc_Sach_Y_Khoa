# from collections import Counter
# import fitz  # Thư viện PyMuPDF

# # Đường dẫn tới file PDF của bạn
# pdf_path = "Gray_Anatomy_p265.pdf"

# doc = fitz.open(pdf_path)

# print("=== KẾT QUẢ KIỂM TRA HÌNH ẢNH THEO TRANG ===")

# for page_num in range(len(doc)):
#     page = doc[page_num]
#     image_list = page.get_images()

#     # Lưu kích thước (width, height) của tất cả ảnh trong trang hiện tại
#     sizes_in_page = []

#     for img_info in image_list:
#         xref = img_info[0]
#         base_image = doc.extract_image(xref)

#         width = base_image["width"]
#         height = base_image["height"]

#         sizes_in_page.append((width, height))

#     # Đếm số lần xuất hiện của từng kích thước trong trang
#     size_counts = Counter(sizes_in_page)

#     # Lọc ra các kích thước xuất hiện >= 2 lần
#     duplicates = {size: count for size, count in size_counts.items() if count >= 2}

#     # Nếu trang có ảnh trùng kích thước thì in ra
#     if duplicates:
#         print(f"\nTrang {page_num + 1}:")
#         for size, count in duplicates.items():
#             print(
#                 f"  - Kích thước {size[0]} x {size[1]} px: xuất hiện {count} lần"
#             )

# doc.close()



# //////////////////////////////////////////////////// LẦN 2
# from collections import Counter
# import os
# import fitz  # PyMuPDF

# # 1. Đường dẫn tới file PDF của bạn
# # pdf_path = "Gray_Anatomy_p265.pdf"
# pdf_path = "Gray_Anatomy_p266.pdf"

# # 2. Tạo thư mục để lưu các ảnh trùng kích thước
# output_dir = "trung_kich_thuoc"
# os.makedirs(output_dir, exist_ok=True)

# doc = fitz.open(pdf_path)

# print("=== BÁO CÁO HÌNH ẢNH TRÙNG KÍCH THƯỚC TRÊN CÙNG TRANG ===")

# for page_num in range(len(doc)):
#     page = doc[page_num]
#     image_list = page.get_images()

#     # Thu thập thông tin chi tiết của tất cả ảnh trên trang
#     images_info = []
#     sizes_only = []

#     for img in image_list:
#         xref = img[0]
#         img_name = img[7]  # Tên đối tượng ảnh trong PDF
#         base_image = doc.extract_image(xref)

#         width = base_image["width"]
#         height = base_image["height"]
#         ext = base_image["ext"]  # Định dạng ảnh (png, jpeg,...)
#         image_bytes = base_image["image"]

#         info = {
#             "xref": xref,
#             "name": img_name,
#             "size": (width, height),
#             "ext": ext,
#             "bytes": image_bytes,
#         }
#         images_info.append(info)
#         sizes_only.append((width, height))

#     # Đếm số lần xuất hiện của từng kích thước
#     size_counts = Counter(sizes_only)
#     duplicate_sizes = {
#         size for size, count in size_counts.items() if count >= 2
#     }

#     # Nếu có ảnh trùng kích thước trên trang hiện tại
#     if duplicate_sizes:
#         print(f"\n📍 Trang {page_num + 1}:")

#         for size in duplicate_sizes:
#             count = size_counts[size]
#             print(
#                 f"  👉 Kích thước {size[0]} x {size[1]} px (Xuất hiện {count} lần):"
#             )

#             # Lọc danh sách các ảnh có kích thước này
#             matching_images = [
#                 img for img in images_info if img["size"] == size
#             ]

#             for idx, img in enumerate(matching_images, start=1):
#                 # In thông tin ID và Tên ảnh ra console
#                 print(
#                     f"     - Ảnh {idx}: ID (xref) = {img['xref']} | Tên (Name) = {img['name']}"
#                 )

#                 # Lưu file ảnh ra thư mục kiểm tra
#                 # Tên file lưu: Trang[X]_KichThuoc_ID.định_dạng
#                 file_name = f"Trang{page_num + 1}_{size[0]}x{size[1]}_xref{img['xref']}.{img['ext']}"
#                 save_path = os.path.join(output_dir, file_name)

#                 # Ghi ảnh ra đĩa (nếu chưa tồn tại)
#                 if not os.path.exists(save_path):
#                     with open(save_path, "wb") as f:
#                         f.write(img["bytes"])

# doc.close()

# print(
#     f"\n✅ Hoàn tất! Tất cả các ảnh trùng đã được lưu tại thư mục: '{output_dir}'"
# )



# ////////////////////////////////////////////// LẦN 3
from collections import defaultdict
import hashlib
import fitz  # PyMuPDF

pdf_path = "Gray_Anatomy_p265.pdf"
doc = fitz.open(pdf_path)

print("=== BÁO CÁO NHÓM DỰA TRÊN ẢNH GỐC (GIỐNG ASPOSE) ===")

for page_num in range(len(doc)):
    page = doc[page_num]
    image_list = page.get_images()

    # Nhóm các đối tượng ảnh theo "Dấu vết dữ liệu" (Image Hash)
    # Cấu trúc: image_groups[hash_code] = list các vị trí/thông tin
    image_groups = defaultdict(list)

    for img in image_list:
        xref = img[0]
        img_name = img[7]  # Tên đối tượng (ví dụ: QQAPIme8e4ec74)

        # Trích xuất dữ liệu nhị phân của tệp ảnh để tạo mã MD5 nhận diện tuyệt đối
        base_image = doc.extract_image(xref)
        image_bytes = base_image["image"]
        img_hash = hashlib.md5(
            image_bytes
        ).hexdigest()  # Mã băm đại diện cho bức ảnh

        # Lấy tọa độ hiển thị của hình ảnh này trên trang
        rects = page.get_image_rects(xref)

        image_groups[img_hash].append(
            {
                "xref": xref,
                "name": img_name,
                "size": (base_image["width"], base_image["height"]),
                "rects": rects,
            }
        )

    # Hiển thị kết quả nhóm lại
    print(f"\n📍 Trang {page_num + 1}:")
    for img_hash, occurrences in image_groups.items():
        count = len(occurrences)

        # Lấy thông tin đại diện của ảnh gốc trong nhóm
        sample_img = occurrences[0]
        main_name = sample_img["name"]
        width, height = sample_img["size"]

        # Nếu ảnh này xuất hiện từ 2 lần trở lên trên trang (như watermark)
        if count >= 2:
            print(f"  🟢 [Watermark phát hiện] Ảnh gốc tên: '{main_name}'")
            print(f"     - Kích thước: {width} x {height} px")
            print(f"     - Số lần xuất hiện (Occurrences): {count} lần")
            print("     - Chi tiết các vị trí (IDs):")

            for idx, item in enumerate(occurrences, 1):
                print(f"       + Vị trí {idx}: ID (xref) = {item['xref']}")

doc.close()