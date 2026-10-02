"""
Script trích xuất raw_json từ các file PDF đã tách trang (page-0001.pdf, page-0002.pdf, ...)
Vừa ghi ra file JSON, vừa lưu nội dung vào cột `raw_json` (JSON) trong bảng `pages`.

Yêu cầu cài đặt:
    pip install pymupdf mysql-connector-python --break-system-packages

CÁCH DÙNG:
    python3 extract_raw_json.py
"""

import os
import re
import json
import glob
import fitz  # PyMuPDF
import mysql.connector
from mysql.connector import Error as MySQLError

# =========================================================================
# CONFIG - chỉnh lại cho đúng môi trường của bạn
# =========================================================================

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "doc_sach_y_khoa",
}

BOOK_TITLE = "Gray's Anatomy for Students 4th"
BOOK_SLUG = "grays-anatomy-4th"

# BASE_DIR = r"D:\00000_Y_DA_KHOA\SACH_PDF_FOR_WEB_DOC_SACH"
BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

# Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
PAGES_DIR = os.path.join(BASE_DIR, "Grays_Anatomy_(Annas_Archive)_pages")

# Thư mục lưu file raw_json output
JSONS_DIR = os.path.join(BASE_DIR, "Grays_Anatomy_(Annas_Archive)_jsons")

# Nếu True: bỏ qua trang đã có raw_json trong DB (không xử lý lại)
SKIP_IF_ALREADY_EXTRACTED = True

# Pattern để lấy số trang từ tên file, VD: page-0294.pdf -> 294
FILENAME_PATTERN = re.compile("page-(\d+)\.pdf$", re.IGNORECASE)


# =========================================================================
# DATABASE HELPERS
# =========================================================================

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def get_or_create_book(conn, title, slug, source_pdf_path=None):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id FROM books WHERE slug = %s", (slug,))
    row = cursor.fetchone()

    if row:
        cursor.close()
        return row["id"]

    cursor.execute(
        """
        INSERT INTO books (title, slug, source_pdf_path, created_at, updated_at)
        VALUES (%s, %s, %s, NOW(), NOW())
        """,
        (title, slug, source_pdf_path),
    )
    conn.commit()
    book_id = cursor.lastrowid
    cursor.close()
    print(f"-> Đã tạo mới book_id={book_id} cho sách '{title}'")
    return book_id


def get_existing_page(conn, book_id, page_number):
    """Kiểm tra xem trang đã được extract chưa (để hỗ trợ resume)."""
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT id, status FROM pages WHERE book_id = %s AND page_number = %s",
        (book_id, page_number),
    )
    row = cursor.fetchone()
    cursor.close()
    return row


def upsert_page_raw_json(conn, book_id, page_number, raw_json_data, status, error_message=None):
    """
    Insert hoặc update record của 1 trang trong bảng pages.
    Lưu raw_json trực tiếp vào cột JSON (Laravel cast 'array' sẽ tự đọc được).
    """
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


def update_book_total_pages(conn, book_id, total_pages):
    cursor = conn.cursor()
    cursor.execute(
        "UPDATE books SET total_pages = %s, updated_at = NOW() WHERE id = %s",
        (total_pages, book_id),
    )
    conn.commit()
    cursor.close()


# =========================================================================
# PDF EXTRACTION
# =========================================================================

def get_span_style(span):
    """Giải mã flags bitmask của PyMuPDF thành bold/italic dễ đọc."""
    flags = span.get("flags", 0)
    return {
        "bold": bool(flags & 2 ** 4),
        "italic": bool(flags & 2 ** 1),
        "font": span.get("font"),
        "size": round(span.get("size", 0), 1),
        "color": span.get("color"),
    }


def extract_raw_data(pdf_path):
    """
    Mở 1 file PDF (1 trang), trích xuất toàn bộ dữ liệu cần thiết:
    - text block/line/span (kèm style bold/italic)
    - danh sách ảnh raster nhúng sẵn (vị trí + xref)
    - danh sách vector drawings (để nhận diện vùng tô màu/highlight)
    - highlight annotation thật (nếu có)
    """
    doc = fitz.open(pdf_path)
    page = doc[0]  # mỗi file chỉ có đúng 1 trang

    page_dict = page.get_text("dict", flags=fitz.TEXT_DEHYPHENATE) # "flags=fitz.TEXT_DEHYPHENATE" giúp nối các từ bị ngắt dòng bằng dấu gạch ngang

    for block in page_dict.get("blocks", []):
        if block.get("type") != 0:  # 0 = text block, 1 = image block
            continue
        for line in block.get("lines", []):
            for span in line.get("spans", []):
                span["style"] = get_span_style(span)

    image_info = page.get_image_info(hashes=False)

    drawings = page.get_drawings()
    drawing_summary = [
        {
            "rect": list(d["rect"]) if d.get("rect") else None,
            "fill": d.get("fill"),
            "stroke": d.get("color"),
        }
        for d in drawings
        if d.get("fill") is not None
    ]

    annots = []
    for annot in page.annots() or []:
        if annot.type[1] == "Highlight":
            annots.append({
                "rect": list(annot.rect),
                "colors": annot.colors,
            })

    raw_data = {
        "page_width": page.rect.width,
        "page_height": page.rect.height,
        "blocks": page_dict.get("blocks", []),
        "images": image_info,
        "fill_drawings": drawing_summary,
        "highlight_annots": annots,
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

    book_id = get_or_create_book(conn, BOOK_TITLE, BOOK_SLUG)

    page_files = list_page_files(PAGES_DIR)
    total = len(page_files)
    print(f"-> Tìm thấy {total} file trang trong: {PAGES_DIR}")

    success_count = 0
    skip_count = 0
    fail_count = 0

    for idx, pdf_path in enumerate(page_files, start=1):
        m = FILENAME_PATTERN.search(os.path.basename(pdf_path))
        page_number = int(m.group(1))

        existing = get_existing_page(conn, book_id, page_number)
        if (
            SKIP_IF_ALREADY_EXTRACTED
            and existing
            and existing["status"] not in ("pending", "failed")
        ):
            skip_count += 1
            continue

        try:
            raw_data = extract_raw_data(pdf_path)

            # Ghi ra file (giữ để backup/debug ngoài DB, mở tay khi cần)
            json_filename = f"page-{page_number:04d}_raw.json"
            json_path = os.path.join(JSONS_DIR, json_filename)
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump(raw_data, f, ensure_ascii=False, indent=2)

            # Lưu đồng thời vào DB (cột raw_json kiểu JSON)
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

    update_book_total_pages(conn, book_id, total)
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