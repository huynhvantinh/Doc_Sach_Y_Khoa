import os
import re

# 1. Đường dẫn thư mục mới trong WSL
folder_path = r"/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/0_Test_Tong_Hop_pages_2"
os.chdir(folder_path)

# Số bắt đầu mong muốn
start_num = 230

# 2. Lấy danh sách các file 'page-*.pdf'
files = [f for f in os.listdir('.') if f.startswith('page-') and f.endswith('.pdf')]

def extract_number(filename):
    match = re.search(r'page-(\d+)\.pdf', filename)
    return int(match.group(1)) if match else 0

# Sắp xếp các file theo đúng thứ tự số nhỏ -> lớn
files.sort(key=extract_number)

if not files:
    print("Không tìm thấy file 'page-*.pdf' nào trong thư mục!")
else:
    print(f"Tìm thấy {len(files)} file. Đang tiến hành đổi tên...")

    # BƯỚC 1: Đổi tên sang file tạm thời để tránh xung đột tên cũ - mới
    temp_files = []
    for index, old_name in enumerate(files):
        temp_name = f"__temp_{index}.pdf"
        os.rename(old_name, temp_name)
        temp_files.append((temp_name, old_name))

    # BƯỚC 2: Đổi từ tên tạm thời sang tên chính thức liên tục từ page-0202.pdf
    current_num = start_num
    for temp_name, original_name in temp_files:
        new_name = f"page-{current_num:04d}.pdf"
        os.rename(temp_name, new_name)
        print(f"Đã đổi: {original_name} -> {new_name}")
        current_num += 1

    print("\n----------------------------------------")
    print(f"Hoàn tất! Danh sách đã đánh số liên tục từ page-{start_num:04d}.pdf đến page-{(current_num - 1):04d}.pdf")