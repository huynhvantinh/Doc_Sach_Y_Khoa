#  Ảnh vhuyeern qua RGB bị đậm hơn

import os
import io
from PIL import Image, ImageCms

# Thư mục nguồn và đích trên WSL (đã map từ ổ D:\ của Windows)
SRC_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images"
DST_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_(Annas_Archive)_images_rgb"

def convert_cmyk_jpeg_to_rgb(cmyk_bytes: bytes) -> bytes:
    """
    Convert JPEG CMYK sang RGB có quản lý màu đúng cách (dùng ICC profile nếu có).
    """
    img = Image.open(io.BytesIO(cmyk_bytes))
    
    if img.mode == "CMYK":
        icc_raw = img.info.get("icc_profile")
        if icc_raw:
            try:
                src_profile = ImageCms.ImageCmsProfile(io.BytesIO(icc_raw))
                dst_profile = ImageCms.createProfile("sRGB")
                img = ImageCms.profileToProfile(img, src_profile, dst_profile, outputMode="RGB")
            except Exception:
                # Nếu profile nhúng bị lỗi/corrupted, fallback về convert chuẩn của Pillow
                img = img.convert("RGB")
        else:
            img = img.convert("RGB")
    elif img.mode != "RGB":
        # Đảm bảo các mode khác (RGBA, L, P) cũng đưa về RGB sạch
        img = img.convert("RGB")

    output = io.BytesIO()
    img.save(output, format="JPEG", quality=95)
    return output.getvalue()


def process_directory(src_folder: str, dst_folder: str):
    os.makedirs(dst_folder, exist_ok=True)
    
    cmyk_count = 0
    rgb_count = 0
    other_count = 0
    total_files = 0

    print("🔍 Đang kiểm tra và xử lý thư mục ảnh...\n")

    files = [f for f in os.listdir(src_folder) if f.lower().endswith(('.jpg', '.jpeg', '.png', '.tif', '.tiff'))]
    
    for filename in files:
        total_files += 1
        src_path = os.path.join(src_folder, filename)
        dst_path = os.path.join(dst_folder, filename)
        
        try:
            with open(src_path, "rb") as f:
                raw_bytes = f.read()

            # Mở file kiểm tra mode màu ban đầu
            with Image.open(io.BytesIO(raw_bytes)) as check_img:
                mode = check_img.mode

            if mode == "CMYK":
                cmyk_count += 1
                converted_bytes = convert_cmyk_jpeg_to_rgb(raw_bytes)
                with open(dst_path, "wb") as f_out:
                    f_out.write(converted_bytes)
            elif mode == "RGB":
                rgb_count += 1
                # Ảnh đã là RGB -> Ghi thẳng byte gốc sang thư mục mới để giữ nguyên chất lượng
                with open(dst_path, "wb") as f_out:
                    f_out.write(raw_bytes)
            else:
                other_count += 1
                converted_bytes = convert_cmyk_jpeg_to_rgb(raw_bytes)
                with open(dst_path, "wb") as f_out:
                    f_out.write(converted_bytes)

        except Exception as e:
            print(f"❌ Lỗi khi xử lý file {filename}: {e}")

    print("=" * 45)
    print("📊 BÁO CÁO KẾT QUẢ THỐNG KÊ")
    print("=" * 45)
    print(f"📁 Tổng số file ảnh quét được : {total_files}")
    print(f"🎨 Số ảnh CMYK (đã convert)   : {cmyk_count}")
    print(f"✅ Số ảnh đã là RGB sẵn       : {rgb_count}")
    if other_count > 0:
        print(f"⚠️ Số ảnh hệ màu khác (L/RGBA): {other_count}")
    print("=" * 45)
    print(f"✨ Tất cả ảnh đã được lưu tại : {dst_folder}")


if __name__ == "__main__":
    process_directory(SRC_DIR, DST_DIR)