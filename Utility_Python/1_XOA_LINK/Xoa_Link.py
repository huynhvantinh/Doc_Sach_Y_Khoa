import fitz  # PyMuPDF

# --- CẤU HÌNH ---
input_pdf = (
    # "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_WATERMARK.pdf"
    "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy.pdf"
)
output_pdf = "/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_NO_URL.pdf"

doc = fitz.open(input_pdf)
print("🔍 Đang quét và gỡ bỏ tất cả các Link ẩn / Link nhảy trang web...")

total_links_removed = 0

for page_num in range(len(doc)):
    page = doc[page_num]

    # Lấy danh sách tất cả các liên kết trên trang hiện tại
    links = page.get_links()

    if links:
        links_on_page = 0
        for link in links:
            # Nếu liên kết này chứa đường dẫn Web (URI)
            if "uri" in link or link.get("kind") == fitz.LINK_URI:
                page.delete_link(link)  # Xóa bỏ liên kết
                total_links_removed += 1
                links_on_page += 1

        if links_on_page > 0:
            print(
                f"  ❌ Trang {page_num + 1}: Đã gỡ {links_on_page} link ẩn/web."
            )

# Lưu thành file PDF sạch
doc.save(output_pdf)
doc.close()

print(f"\n✅ HOÀN TẤT!")
print(f"1. Tổng số liên kết web/link ẩn đã xóa: {total_links_removed}")
print(f"2. File sạch đã được lưu tại: '{output_pdf}'")
print("👉 Giờ bạn có thể mở file mới ra, bôi đen chữ bình thường và không sợ bị click nhảy sang web khác nữa!")




