import os
import io
import subprocess
from PIL import Image, ImageOps

SRC_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images"
DST_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images_rgb"

def convert_cmyk_file(src_path, dst_path):
    """
    Sử dụng ImageMagick CLI từ Python để đảm bảo 100% ảnh không bị đen và chuẩn màu Windows Photo.
    """
    cmd = [
        "convert",
        src_path,
        "-colorspace", "sRGB",
        "-quality", "95",
        dst_path
    ]
    subprocess.run(cmd, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

def process_directory(src_folder: str, dst_folder: str):
    os.makedirs(dst_folder, exist_ok=True)
    
    cmyk_count = 0
    rgb_count = 0
    other_count = 0
    error_count = 0
    total_files = 0

    print("🚀 Bắt đầu xử lý chuyển đổi màu ảnh bằng ImageMagick Engine...")
    print("-" * 60)

    valid_extensions = ('.jpg', '.jpeg', '.png', '.tif', '.tiff')
    files = [f for f in os.listdir(src_folder) if f.lower().endswith(valid_extensions)]
    
    for filename in files:
        total_files += 1
        src_path = os.path.join(src_folder, filename)
        dst_path = os.path.join(dst_folder, filename)
        
        try:
            with Image.open(src_path) as check_img:
                mode = check_img.mode

            if mode == "CMYK":
                cmyk_count += 1
                convert_cmyk_file(src_path, dst_path)
                print(f"🔄 [{total_files}] Đã convert CMYK -> RGB (Chuẩn màu): {filename}")

            elif mode == "RGB":
                rgb_count += 1
                # Ảnh đã là RGB -> copy byte gốc giữ 100% chất lượng
                with open(src_path, "rb") as f_in, open(dst_path, "wb") as f_out:
                    f_out.write(f_in.read())
                print(f"✅ [{total_files}] Đã là RGB sẵn: {filename}")

            else:
                other_count += 1
                convert_cmyk_file(src_path, dst_path)
                print(f"⚠️ [{total_files}] Đã convert {mode} -> RGB: {filename}")

        except Exception as e:
            error_count += 1
            print(f"❌ [{total_files}] Lỗi khi xử lý file {filename}: {e}")

    # Báo cáo thống kê
    print("\n" + "=" * 60)
    print("📊 BÁO CÁO THỐNG KÊ KẾT QUẢ CHUYỂN ĐỔI")
    print("=" * 60)
    print(f"📁 Tổng số file ảnh quét được  : {total_files}")
    print(f"🔄 Số ảnh CMYK (Đã xử lý màu) : {cmyk_count}")
    print(f"✅ Số ảnh đã là RGB sẵn        : {rgb_count}")
    if other_count > 0:
        print(f"⚠️ Số ảnh hệ màu khác (L/RGBA) : {other_count}")
    if error_count > 0:
        print(f"❌ Số ảnh bị lỗi không đọc được: {error_count}")
    print("=" * 60)
    print(f"✨ Tất cả ảnh RGB đã lưu tại: {dst_folder}\n")

if __name__ == "__main__":
    process_directory(SRC_DIR, DST_DIR)