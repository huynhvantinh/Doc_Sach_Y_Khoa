"""
FILE 2: Trích xuất từng trang PDF thành 2 thứ:
  - JSON : chỉ chứa TEXT (blocks) + kích thước trang + highlight annotation
           -> lưu vào file .json và cột raw_json của bảng `pages`
  - SVG  : mọi thứ còn lại (ảnh, ô màu, đường kẻ...), ĐÃ XÓA TEXT
           -> lưu ra file page-XXXX.svg (Laravel tự tìm theo số trang)

FLOW (xem hàm main):
  1. Kết nối DB, lấy book_id
  2. Duyệt từng file page-XXXX.pdf, bỏ qua trang đã extract xong
  3. extract_text_data()      : lấy text + highlight (PDF gốc, chưa bị sửa)
  4. render_svg_without_text(): redaction xóa text rồi xuất SVG (mở PDF riêng)
  5. Ghi file SVG + JSON, upsert vào DB

Cài đặt (cần PyMuPDF mới, >= 1.25 để có tham số text= của apply_redactions):
    pip install -U pymupdf mysql-connector-python --break-system-packages
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

BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

BOOK_SLUG = "test-tong-hop"  # phải khớp với slug đã tạo ở file 1
PAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_pages")  # chứa page-0001.pdf, ...
JSONS_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_jsons")  # output: page-XXXX_raw.json
SVGS_DIR  = os.path.join(BASE_DIR, "0_Test_Tong_Hop_svgs")   # output: page-XXXX.svg

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "doc_sach_y_khoa",
}

# True:  Bỏ qua trang đã extract xong (status khác pending/failed).
# False: Muốn chạy lại toàn bộ sách (vd sau khi đổi cách lưu SVG) thì đặt False
SKIP_IF_ALREADY_EXTRACTED = True

# None: chạy hết.
# Đặt số trang (vd 140) để chạy thử đúng 1 trang (luôn chạy lại, bỏ qua status cũ)
TEST_SINGLE_PAGE = None

FILENAME_PATTERN = re.compile(r"page-(\d+)\.pdf$", re.IGNORECASE)


# =========================================================================
# DATABASE
# =========================================================================

def get_book_id(conn, slug):
    """Lấy id sách theo slug. Báo lỗi rõ ràng nếu chưa chạy file 1."""
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id FROM books WHERE slug = %s", (slug,))
    row = cursor.fetchone()
    cursor.close()
    if not row:
        raise RuntimeError(f"Không tìm thấy sách với slug='{slug}'. Hãy chạy file 1 trước.")
    return row["id"]


def get_page_status(conn, book_id, page_number):
    """Trả về status hiện tại của trang trong DB, hoặc None nếu chưa có record."""
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT status FROM pages WHERE book_id = %s AND page_number = %s",
        (book_id, page_number),
    )
    row = cursor.fetchone()
    cursor.close()
    return row["status"] if row else None


def upsert_page(conn, book_id, page_number, raw_data, status, error_message=None):
    """Insert hoặc update 1 trang. raw_data=None khi trang bị lỗi."""
    raw_json_str = json.dumps(raw_data, ensure_ascii=False) if raw_data is not None else None
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
# TRÍCH XUẤT PDF
# =========================================================================

def extract_text_data(pdf_path):
    """
    Lấy dữ liệu để lưu JSON: kích thước trang, text blocks, highlight annotation.
    Phải chạy trên PDF CHƯA bị redact (nên mở file riêng, tách biệt với hàm SVG).
    """
    doc = fitz.open(pdf_path)
    page = doc[0]  # mỗi file chỉ có đúng 1 trang

    # Chỉ giữ block chữ (type=0). Block ảnh (type=1) chứa bytes không serialize
    # được ra JSON, và ảnh đã nằm sẵn trong SVG nên không cần giữ.
    text_blocks = [b for b in page.get_text("dict")["blocks"] if b.get("type") == 0]

    highlight_annots = [
        {"rect": list(a.rect), "colors": a.colors}
        for a in (page.annots() or [])
        if a.type[1] == "Highlight"
    ]

    raw_data = {
        "page_width": page.rect.width,
        "page_height": page.rect.height,
        "blocks": text_blocks,
        "highlight_annots": highlight_annots,
    }
    doc.close()
    return raw_data


def render_svg_without_text(pdf_path):
    """
    Xuất trang thành SVG đã bỏ text bằng cách redaction ngay trong PDF:
      - fill=False              : không tô trắng vùng redact (giữ nguyên nền)
      - IMAGE_NONE              : giữ ảnh
      - LINE_ART_NONE           : giữ đường vẽ / vector
      - TEXT_REMOVE             : chỉ xóa text
    Redaction chỉ sửa bản trong bộ nhớ, KHÔNG gọi doc.save() nên file gốc an toàn.
    Ảnh được nhúng base64 trong SVG, MuPDF đã tự convert CMYK -> RGB.
    """
    doc = fitz.open(pdf_path)
    page = doc[0]

    # Tạo redaction cho toàn bộ vùng text: Chỉ xóa text, giữ nguyên ảnh + vector
    # page.add_redact_annot(page.rect)
    page.add_redact_annot(page.rect, fill=False) # nếu không đặt fill=False có thể tô trắng đè lên nền
    page.apply_redactions(
        images=fitz.PDF_REDACT_IMAGE_NONE,        # giữ ảnh
        graphics=fitz.PDF_REDACT_LINE_ART_NONE,   # giữ đường vẽ/vector
        text=fitz.PDF_REDACT_TEXT_REMOVE,         # xóa text
    )

    svg = page.get_svg_image()
    doc.close()
    return svg


# =========================================================================
# MAIN
# =========================================================================

def main():
    # Kiểm tra phiên bản PyMuPDF có hỗ trợ redaction kiểu mới không
    if not hasattr(fitz, "PDF_REDACT_TEXT_REMOVE"):
        print("❌ PyMuPDF quá cũ. Chạy: pip install -U pymupdf --break-system-packages")
        return

    os.makedirs(JSONS_DIR, exist_ok=True)
    os.makedirs(SVGS_DIR, exist_ok=True)

    # ---- Bước 1: kết nối DB, lấy book_id ----
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
    except MySQLError as e:
        print(f"❌ Không kết nối được MySQL: {e}")
        return

    try:
        book_id = get_book_id(conn, BOOK_SLUG)
    except RuntimeError as e:
        print(f"❌ {e}")
        conn.close()
        return

    # ---- Bước 2: liệt kê file trang, sắp xếp theo số trang ----
    page_files = []
    for path in glob.glob(os.path.join(PAGES_DIR, "page-*.pdf")):
        m = FILENAME_PATTERN.search(os.path.basename(path))
        if m:
            page_files.append((int(m.group(1)), path))
    page_files.sort()

    total = len(page_files)
    print(f"-> book_id={book_id}, tìm thấy {total} file trang trong: {PAGES_DIR}")

    success_count = skip_count = fail_count = 0

    # ---- Bước 3: xử lý từng trang ----
    for idx, (page_number, pdf_path) in enumerate(page_files, start=1):

        # Chế độ test 1 trang: bỏ qua mọi trang khác, luôn chạy lại trang này
        if TEST_SINGLE_PAGE is not None and page_number != int(TEST_SINGLE_PAGE):
            continue

        # Bỏ qua trang đã extract xong
        if TEST_SINGLE_PAGE is None and SKIP_IF_ALREADY_EXTRACTED:
            status = get_page_status(conn, book_id, page_number)
            if status is not None and status not in ("pending", "failed"):
                skip_count += 1
                continue

        try:
            raw_data = extract_text_data(pdf_path)   # text + highlight (PDF gốc)
            svg = render_svg_without_text(pdf_path)  # nền vector + ảnh, không text

            # Cảnh báo nếu SVG vẫn còn chữ (chữ dạng outline trong PDF gốc thì không xóa được)
            if "<text" in svg:
                print(f"⚠️  Trang {page_number}: SVG vẫn còn thẻ <text>, kiểm tra lại.")

            svg_path = os.path.join(SVGS_DIR, f"page-{page_number:04d}.svg")
            with open(svg_path, "w", encoding="utf-8") as f:
                f.write(svg)

            json_path = os.path.join(JSONS_DIR, f"page-{page_number:04d}_raw.json")
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump(raw_data, f, ensure_ascii=False, indent=2)

            upsert_page(conn, book_id, page_number, raw_data, "extracted")
            success_count += 1

        except Exception as e:
            print(f"⚠️  Lỗi ở trang {page_number}: {e}")
            upsert_page(conn, book_id, page_number, None, "failed", str(e))
            fail_count += 1

        if idx % 50 == 0 or idx == total:
            print(f"   Tiến độ: {idx}/{total} (thành công: {success_count}, bỏ qua: {skip_count}, lỗi: {fail_count})")

    conn.close()

    print("=" * 60)
    print("🎉 HOÀN TẤT")
    print(f"   Tổng số trang:  {total}")
    print(f"   Thành công:     {success_count}")
    print(f"   Bỏ qua (đã có): {skip_count}")
    print(f"   Lỗi:            {fail_count}")
    if fail_count > 0:
        print("   -> Xem cột error_message trong bảng `pages` (status='failed') để biết chi tiết.")


if __name__ == "__main__":
    main()