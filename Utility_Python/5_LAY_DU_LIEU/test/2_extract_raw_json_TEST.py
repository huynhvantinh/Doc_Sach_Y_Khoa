"""
FILE 2: Trích xuất dữ liệu THÔ (raw) từ từng trang PDF.
Chỉ lấy đúng dữ liệu PyMuPDF trả về (blocks, images, fill_drawings, highlight_annots),
KHÔNG xử lý/diễn giải style (bold/italic/highlight) - việc đó thuộc về file 3.

Cũng trích xuất ảnh THẬT (pixel data) của mỗi trang, convert CMYK -> RGB, lưu ra file.

Yêu cầu cài đặt:
    pip install pymupdf mysql-connector-python --break-system-packages

Cách chạy (sau khi đã chạy file 1):
    python3 2_extract_raw_json.py
"""

import os
import re
import json
import glob
import fitz  # PyMuPDF
import mysql.connector
from mysql.connector import Error as MySQLError

# =========================================================================
# CONFIG - đổi các giá trị này khi làm sách khác
# =========================================================================

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "doc_sach_y_khoa",
}

BOOK_SLUG = "grays-anatomy-4th"  # phải khớp với BOOK_SLUG đã tạo ở file 1

BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

# Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
PAGES_DIR = os.path.join(BASE_DIR, "Grays_Anatomy_(Annas_Archive)_pages")

# Thư mục lưu file raw_json output
JSONS_DIR = os.path.join(BASE_DIR, "Grays_Anatomy_(Annas_Archive)_jsons")

# Thư mục lưu file ảnh thật trích ra từ PDF (đã convert sang RGB để hiển thị web)
IMAGES_DIR = os.path.join(BASE_DIR, "Grays_Anatomy_(Annas_Archive)_images")

# Nếu True: bỏ qua trang đã extract xong (status khác pending/failed)
SKIP_IF_ALREADY_EXTRACTED = True

FILENAME_PATTERN = re.compile(r"page-(\d+)\.pdf$", re.IGNORECASE)


# =========================================================================
# DATABASE HELPERS
# =========================================================================

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def get_book_id(conn, slug):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id FROM books WHERE slug = %s", (slug,))
    row = cursor.fetchone()
    cursor.close()
    if not row:
        raise RuntimeError(
            f"Không tìm thấy sách với slug='{slug}'. Hãy chạy file 1 (1_create_book.py) trước."
        )
    return row["id"]


def get_existing_page(conn, book_id, page_number):
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT id, status FROM pages WHERE book_id = %s AND page_number = %s",
        (book_id, page_number),
    )
    row = cursor.fetchone()
    cursor.close()
    return row


def upsert_page_raw_json(conn, book_id, page_number, raw_json_data, status, error_message=None):
    """Insert hoặc update record trong bảng pages, lưu raw_json trực tiếp vào cột JSON."""
    raw_json_str = json.dumps(raw_json_data, ensure_ascii=False) if raw_json_data is not None else None

    cursor = conn.cursor()
    cursor.execute(
        """
        INSERT INTO pages (book_id, page_number, raw_json, status, error_message, created_at, updated_at)
        VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            raw_json = VALUES(raw_json),
            status = VALUES(status),
            error_message = VALUES(error_message),
            updated_at = NOW()
        """,
        (book_id, page_number, raw_json_str, status, error_message),
    )
    conn.commit()
    cursor.close()


# =========================================================================
# PDF EXTRACTION - CHỈ LẤY DỮ LIỆU THÔ, KHÔNG XỬ LÝ STYLE
# =========================================================================

def extract_images_with_files(page, doc, page_number):
    """
    Trích ảnh THẬT từ trang, giữ NGUYÊN VẸN bytes gốc và extension gốc trong PDF
    (không giải nén lại, không convert màu - đúng dữ liệu như trong file PDF).
    - xrefs=True để có xref của từng ảnh.
    - doc.extract_image(xref) trả về bytes nén gốc (jpeg/jp2/png...) + "ext" thật.
    - Gắn thêm field "image_ext" vào metadata gốc (KHÔNG lưu "image_path" vào JSON,
      vì thư mục lưu ảnh có thể đổi sau này - tên file suy ra được từ page_number + "number" (số thứ tự ảnh) + "image_ext" theo quy tắc cố định).

    Lưu ý: ảnh hệ màu CMYK lưu ra sẽ KHÔNG hiển thị đúng màu nếu render thẳng
    trên trình duyệt. Việc convert sang RGB (nếu cần) nên làm ở bước xử lý
    (file 3) hoặc lúc serve ảnh bên Laravel, không làm ở đây.
    """
    os.makedirs(IMAGES_DIR, exist_ok=True)
    image_info_list = page.get_image_info(xrefs=True, hashes=False) #sẽ có thêm thuộc tính digest có kiểu dữ liệu là byte nên không serialize được nên bị lôi, do đó phải xóa key này đi bằng lệnh pop ở dưới
    # image_info_list = page.get_image_info(hashes=False)
    
    for info in image_info_list:
        info.pop("digest", None)

    for idx, info in enumerate(image_info_list, start=1):
        xref = info.get("xref")
        if not xref:
            info["image_ext"] = None
            continue

        try:
            base_image = doc.extract_image(xref)  # bytes gốc, không giải nén/nén lại
            image_bytes = base_image["image"]
            ext = base_image["ext"]  # vd: "jpeg", "png", "jp2", "jpx"...

            filename = f"page-{page_number:04d}_img{idx}.{ext}"
            image_path = os.path.join(IMAGES_DIR, filename)  # chỉ dùng để ghi file, KHÔNG lưu vào JSON

            with open(image_path, "wb") as f:
                f.write(image_bytes)

            info["image_ext"] = ext

        except Exception as e:
            info["image_ext"] = None
            info["extract_error"] = str(e)

    return image_info_list


def extract_raw_data(pdf_path, page_number):
    """
    Lấy đúng dữ liệu PyMuPDF trả về, KHÔNG can thiệp/diễn giải thêm:
    - blocks: nguyên văn từ get_text("dict") - text block/line/span, chưa decode flags
    - images: metadata + đường dẫn ảnh thật đã trích ra file
    - fill_drawings: các vùng vector có tô màu (raw rect + màu)
    - highlight_annots: annotation highlight thật nếu PDF có sẵn
    """
    doc = fitz.open(pdf_path)
    page = doc[0]  # mỗi file chỉ có đúng 1 trang

    page_dict = page.get_text("dict", flags=fitz.TEXT_DEHYPHENATE)

    image_info = extract_images_with_files(page, doc, page_number)

    drawings = page.get_drawings()
    fill_drawings = [
        {
            "rect": list(d["rect"]) if d.get("rect") else None,
            "fill": d.get("fill"),
            "stroke": d.get("color"),
        }
        for d in drawings
        if d.get("fill") is not None
    ]

    highlight_annots = []
    for annot in page.annots() or []:
        if annot.type[1] == "Highlight":
            highlight_annots.append({
                "rect": list(annot.rect),
                "colors": annot.colors,
            })

    raw_data = {
        "page_width": page.rect.width,
        "page_height": page.rect.height,
        "blocks": page_dict.get("blocks", []),
        "images": image_info,
        "fill_drawings": fill_drawings,
        "highlight_annots": highlight_annots,
    }

    doc.close()
    return raw_data


# =========================================================================
# MAIN
# =========================================================================

def list_page_files(pages_dir):
    files = glob.glob(os.path.join(pages_dir, "page-*.pdf"))

    def page_num_of(path):
        m = FILENAME_PATTERN.search(os.path.basename(path))
        return int(m.group(1)) if m else -1

    files = [f for f in files if page_num_of(f) != -1]
    files.sort(key=page_num_of)
    return files


def main():
    os.makedirs(JSONS_DIR, exist_ok=True)

    try:
        conn = get_connection()
    except MySQLError as e:
        print(f"❌ Không kết nối được MySQL: {e}")
        return

    try:
        book_id = get_book_id(conn, BOOK_SLUG)
    except RuntimeError as e:
        print(f"❌ {e}")
        conn.close()
        return

    page_files = list_page_files(PAGES_DIR)
    total = len(page_files)
    print(f"-> book_id={book_id}, tìm thấy {total} file trang trong: {PAGES_DIR}")

    success_count = 0
    skip_count = 0
    fail_count = 0

    for idx, pdf_path in enumerate(page_files, start=1):
        m = FILENAME_PATTERN.search(os.path.basename(pdf_path))
        page_number = int(m.group(1))
        
        
        # ////////////////////////////////////////// TEST
        # --- ĐOẠN CODE TEST 1 TRANG (THÊM VÀO ĐÂY) ---
        TEST_SINGLE_PAGE = 2 #Dùng khi muốn test 1 page bất kì
        if TEST_SINGLE_PAGE is not None and page_number != int(TEST_SINGLE_PAGE):
            continue
        # ---------------------------------------------

        existing = get_existing_page(conn, book_id, page_number)
        # Nếu đang chạy test 1 trang, ép buộc chạy lại trang đó bất chấp status cũ trong DB
        if TEST_SINGLE_PAGE is None and SKIP_IF_ALREADY_EXTRACTED and existing and existing["status"] not in ("pending", "failed"):
            skip_count += 1
            continue
        # ////////////////////////////////////////// TEST

        # existing = get_existing_page(conn, book_id, page_number)
        # if SKIP_IF_ALREADY_EXTRACTED and existing and existing["status"] not in ("pending", "failed"):
        #     skip_count += 1
        #     continue

        try:
            raw_data = extract_raw_data(pdf_path, page_number)

            json_filename = f"page-{page_number:04d}_raw.json"
            json_path = os.path.join(JSONS_DIR, json_filename)
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump(raw_data, f, ensure_ascii=False, indent=2)

            upsert_page_raw_json(
                conn,
                book_id=book_id,
                page_number=page_number,
                raw_json_data=raw_data,
                status="extracted",
            )
            success_count += 1

        except Exception as e:
            error_msg = str(e)
            print(f"⚠️  Lỗi ở trang {page_number}: {error_msg}")
            upsert_page_raw_json(
                conn,
                book_id=book_id,
                page_number=page_number,
                raw_json_data=None,
                status="failed",
                error_message=error_msg,
            )
            fail_count += 1

        if idx % 50 == 0 or idx == total:
            print(f"   Tiến độ: {idx}/{total} (thành công: {success_count}, bỏ qua: {skip_count}, lỗi: {fail_count})")

    conn.close()

    print("=" * 60)
    print(f"🎉 HOÀN TẤT")
    print(f"   Tổng số trang: {total}")
    print(f"   Thành công:    {success_count}")
    print(f"   Bỏ qua (đã có): {skip_count}")
    print(f"   Lỗi:           {fail_count}")
    if fail_count > 0:
        print("   -> Xem cột error_message trong bảng `pages` (status='failed') để biết chi tiết.")


if __name__ == "__main__":
    main()