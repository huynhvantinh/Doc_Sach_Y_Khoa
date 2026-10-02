import os
import re

# 1. Đường dẫn thư mục mới trong WSL
folder_path = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/0_Test_Tong_Hop_pages"
os.chdir(folder_path)

# 2. Lấy danh sách tất cả các file có định dạng 'test-*.pdf'
files = [f for f in os.listdir('.') if f.startswith('test-') and f.endswith('.pdf')]

# Hàm lấy số để sắp xếp đúng thứ tự
def extract_number(filename):
    match = re.search(r'test-(\d+)\.pdf', filename)
    return int(match.group(1)) if match else 0

files.sort(key=extract_number)

if not files:
    print("Không tìm thấy file 'test-*.pdf' nào trong thư mục!")
else:
    print(f"Tìm thấy {len(files)} file. Đang tiến hành đổi tên...")
    
    for old_name in files:
        # Thay thế prefix 'test-' bằng 'page-'
        # Hoặc dùng định dạng lại số giữ nguyên 4 chữ số:
        num = extract_number(old_name)
        new_name = f"page-{num:04d}.pdf"
        
        os.rename(old_name, new_name)
        print(f"Đã đổi: {old_name} -> {new_name}")

    print("\n----------------------------------------")
    print("Hoàn tất đổi tên tất cả các file!")