"""
FILE 2: Trích xuất từng trang PDF thành:
  - JSON  : chỉ chứa TEXT (blocks) + kích thước trang + highlight annotation
  - SVG   : mọi thứ còn lại (ảnh, ô màu, đường kẻ...), ĐÃ XÓA TEXT

Cài đặt:
    pip install pymupdf mysql-connector-python --break-system-packages
"""

import os
import re
import json
import glob
import fitz  # PyMuPDF
import mysql.connector
from mysql.connector import Error as MySQLError
import xml.etree.ElementTree as ET

# ========================= CONFIG =========================
BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

BOOK_SLUG = "test-tong-hop"
PAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_pages")
JSONS_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_jsons")
SVGS_DIR  = os.path.join(BASE_DIR, "0_Test_Tong_Hop_svgs")   # MỚI: thay cho IMAGES_DIR

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "doc_sach_y_khoa",
}

SKIP_IF_ALREADY_EXTRACTED = True
TEST_SINGLE_PAGE = None   # đặt số trang (vd 140) để test 1 trang, None = chạy hết

FILENAME_PATTERN = re.compile(r"page-(\d+)\.pdf$", re.IGNORECASE)

SVG_NS = "http://www.w3.org/2000/svg"
XLINK_NS = "http://www.w3.org/1999/xlink"

# ===================== DATABASE HELPERS (giữ nguyên) =====================
def get_connection():
    return mysql.connector.connect(**DB_CONFIG)

def get_book_id(conn, slug):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id FROM books WHERE slug = %s", (slug,))
    row = cursor.fetchone()
    cursor.close()
    if not row:
        raise RuntimeError(f"Không tìm thấy sách với slug='{slug}'. Hãy chạy file 1 trước.")
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



# ===================== HÀM LƯU SVG KHÔNG TEXT =====================
def build_svg_without_text(page):
    """
    Xuất trang thành SVG rồi xóa toàn bộ text.
    - text_as_path=False: chữ ra dạng <text>/<tspan> (thay vì <path>) nên xóa được.
    - Ảnh được nhúng base64 trong SVG, MuPDF đã tự convert sang RGB.
    - Lưu ý: chữ nào trong PDF thực chất là hình vẽ (outline) thì vẫn còn trong SVG,
      đó là hành vi đúng vì nó không phải text thật.
    """
    svg = page.get_svg_image(text_as_path=False)

    # Bỏ XML declaration để ET.fromstring(str) không bị lỗi encoding
    svg = re.sub(r"^\s*<\?xml[^>]*\?>", "", svg)

    ET.register_namespace("", SVG_NS)
    ET.register_namespace("xlink", XLINK_NS)
    root = ET.fromstring(svg)

    def local(tag):
        return tag.split("}")[-1]

    for parent in list(root.iter()):
        for child in list(parent):
            if local(child.tag) in ("text", "tspan"):
                parent.remove(child)

    return ET.tostring(root, encoding="unicode")

# ===================== TRÍCH XUẤT TEXT VÀ HIGHLIGHT - RỒI LƯU SVG =====================
def extract_page(pdf_path):
    """Trả về (raw_data cho JSON, chuỗi SVG không text)."""
    doc = fitz.open(pdf_path)
    page = doc[0]

    page_dict = page.get_text("dict")
    
    # 1. Lấy text
    # Chỉ giữ block chữ (type=0). Block ảnh (type=1) đã nằm trong SVG.
    text_blocks = [b for b in page_dict.get("blocks", []) if b.get("type") == 0]

    # 2. Lấy highlight
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
        "blocks": text_blocks,
        "highlight_annots": highlight_annots,
    }

    svg = build_svg_without_text(page)
    doc.close()
    return raw_data, svg



# ===================== MAIN =====================
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
    os.makedirs(SVGS_DIR, exist_ok=True)

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

    success_count = skip_count = fail_count = 0

    for idx, pdf_path in enumerate(page_files, start=1):
        page_number = int(FILENAME_PATTERN.search(os.path.basename(pdf_path)).group(1))

        if TEST_SINGLE_PAGE is not None and page_number != int(TEST_SINGLE_PAGE):
            continue

        existing = get_existing_page(conn, book_id, page_number)
        if (TEST_SINGLE_PAGE is None and SKIP_IF_ALREADY_EXTRACTED
                and existing and existing["status"] not in ("pending", "failed")):
            skip_count += 1
            continue

        try:
            raw_data, svg = extract_page(pdf_path)

            # SVG ra file, đặt tên theo quy ước để Laravel tự tìm
            svg_path = os.path.join(SVGS_DIR, f"page-{page_number:04d}.svg")
            with open(svg_path, "w", encoding="utf-8") as f:
                f.write(svg)

            # JSON backup ra file (tùy chọn) + lưu DB
            json_path = os.path.join(JSONS_DIR, f"page-{page_number:04d}_raw.json")
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump(raw_data, f, ensure_ascii=False, indent=2)

            upsert_page_raw_json(conn, book_id, page_number, raw_data, "extracted")
            success_count += 1

        except Exception as e:
            print(f"⚠️  Lỗi ở trang {page_number}: {e}")
            upsert_page_raw_json(conn, book_id, page_number, None, "failed", str(e))
            fail_count += 1

        if idx % 50 == 0 or idx == total:
            print(f"   Tiến độ: {idx}/{total} (thành công: {success_count}, bỏ qua: {skip_count}, lỗi: {fail_count})")

    conn.close()
    print("=" * 60)
    print(f"🎉 HOÀN TẤT | Tổng: {total} | OK: {success_count} | Bỏ qua: {skip_count} | Lỗi: {fail_count}")

if __name__ == "__main__":
    main()