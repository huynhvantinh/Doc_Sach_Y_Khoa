import os
import re
import fitz  # PyMuPDF

# 1. Đường dẫn thư mục chứa các file PDF trong WSL
folder_path = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/0_Test_Tong_Hop_pages"
os.chdir(folder_path)

# 2. Tên file PDF tổng hợp đầu ra
output_pdf = "test_tong_hop.pdf"

# 3. Lấy danh sách các file 'test-*.pdf' (bỏ qua file output nếu đã tồn tại)
files = [
    f for f in os.listdir('.') 
    if f.startswith('test-') and f.endswith('.pdf') and f != output_pdf
]

# Hàm lấy phần số để sắp xếp đúng thứ tự tự nhiên (test-0001, test-0002,...)
def extract_number(filename):
    match = re.search(r'test-(\d+)\.pdf', filename)
    return int(match.group(1)) if match else 0

files.sort(key=extract_number)

if not files:
    print("Không tìm thấy file 'test-*.pdf' nào để gộp!")
else:
    print(f"Đã tìm thấy {len(files)} file PDF. Đang gộp bằng PyMuPDF...")

    # 4. Tạo một tài liệu PDF trống mới
    merged_doc = fitz.open()

    # 5. Duyệt qua từng file và ghép vào tài liệu chính
    for pdf_file in files:
        print(f"Đang thêm: {pdf_file}")
        with fitz.open(pdf_file) as doc:
            merged_doc.insert_pdf(doc)

    # 6. Lưu file PDF tổng hợp (dùng deflate=True để tối ưu/nén dung lượng)
    merged_doc.save(output_pdf, deflate=True)
    merged_doc.close()

    print("\n----------------------------------------")
    print(f"Hoàn tất! File đã được gộp tại:\n{os.path.join(folder_path, output_pdf)}")