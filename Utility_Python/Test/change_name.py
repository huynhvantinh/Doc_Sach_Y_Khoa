# import os
# import re

# # Đường dẫn thư mục trong WSL
# folder_path = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Test_Tong_Hop"

# # Chuyển đến thư mục mục tiêu
# os.chdir(folder_path)

# # Lấy danh sách file khớp với định dạng page-*.pdf
# files = [f for f in os.listdir('.') if f.startswith('page-') and f.endswith('.pdf')]

# # Hàm để tách lấy phần số phục vụ việc sắp xếp chính xác
# def extract_number(filename):
#     match = re.search(r'page-(\d+)\.pdf', filename)
#     return int(match.group(1)) if match else 0

# # Sắp xếp các file theo đúng thứ tự số từ nhỏ đến lớn
# files.sort(key=extract_number)

# # Tiến hành đổi tên
# for index, old_name in enumerate(files, start=1):
#     new_name = f"test-{index:04d}.pdf"
#     os.rename(old_name, new_name)
#     print(f"Đã đổi: {old_name} -> {new_name}")

# print("Hoàn tất đổi tên!")





import os
import re

# 1. Đường dẫn thư mục trong WSL
folder_path = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Test_Tong_Hop"
os.chdir(folder_path)

# 2. Tìm số 'test-xxxx.pdf' lớn nhất hiện có trong thư mục
existing_test_files = [f for f in os.listdir('.') if f.startswith('test-') and f.endswith('.pdf')]
max_existing_num = 0
for f in existing_test_files:
    match = re.search(r'test-(\d+)\.pdf', f)
    if match:
        max_existing_num = max(max_existing_num, int(match.group(1)))

# 3. Đặt số bắt đầu (Tự động nối tiếp số lớn nhất + 1, hoặc tự điền số tùy ý)
# Ví dụ: Nếu có test-0050.pdf thì tự động start_index = 51
start_index = max_existing_num + 1

# Bạn cũng có thể bỏ comment dòng bên dưới để TỰ ĐIỀN số bắt đầu thủ công nếu muốn:
# start_index = 100 

# Giới hạn số lượng đổi tên (Tùy chọn: Để None nếu muốn đổi hết, hoặc ghi số cụ thể)
max_files_to_process = None  # Ví dụ: nếu đặt = 10 thì chỉ đổi tên 10 file đầu tiên

# 4. Lấy danh sách các file 'page-*.pdf' mới thêm vào
new_files = [f for f in os.listdir('.') if f.startswith('page-') and f.endswith('.pdf')]

def extract_number(filename):
    match = re.search(r'page-(\d+)\.pdf', filename)
    return int(match.group(1)) if match else 0

# Sắp xếp danh sách file mới theo thứ tự tăng dần
new_files.sort(key=extract_number)

# Giới hạn số lượng file xử lý nếu có cài đặt end/limit
if max_files_to_process is not None:
    new_files = new_files[:max_files_to_process]

print(f"--- Bắt đầu đổi tên từ: test-{start_index:04d}.pdf ---")

# 5. Thực hiện đổi tên
current_index = start_index
for old_name in new_files:
    new_name = f"test-{current_index:04d}.pdf"
    
    # Kiểm tra tránh đè lên file đã tồn tại
    if os.path.exists(new_name):
        print(f"Cảnh báo: File {new_name} đã tồn tại! Dừng lại để tránh ghi đè.")
        break
        
    os.rename(old_name, new_name)
    print(f"Đã đổi: {old_name} -> {new_name}")
    current_index += 1

print(f"Hoàn tất! File cuối cùng được tạo là: test-{(current_index - 1):04d}.pdf")