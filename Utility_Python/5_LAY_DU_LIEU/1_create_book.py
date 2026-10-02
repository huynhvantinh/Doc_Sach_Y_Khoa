"""
FILE 1: Tạo (hoặc lấy) record sách trong bảng `books`.
Chạy file này ĐẦU TIÊN, trước khi chạy file 2 và file 3.

Yêu cầu cài đặt:
    pip install mysql-connector-python --break-system-packages

Cách chạy:
    python3 1_create_book.py
"""

import os
import glob
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

# BOOK_TITLE = "Gray's Anatomy for Students 4th"
# BOOK_SLUG = "grays-anatomy-4th"

# BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"
# PAGES_DIR = os.path.join(BASE_DIR, "1_Grays_Anatomy_Students_pages") # Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
# SOURCE_PDF_PATH = os.path.join(BASE_DIR, "1_Grays_Anatomy_Students.pdf") # Đường dẫn file PDF gốc đầy đủ (nếu có, để lưu vào cột source_pdf_path)

BOOK_TITLE = "Test Tong Hop"
BOOK_SLUG = "test-tong-hop"

BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"
PAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_pages") # Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
SOURCE_PDF_PATH = os.path.join(BASE_DIR, "0_Test_Tong_Hop.pdf") # Đường dẫn file PDF gốc đầy đủ (nếu có, để lưu vào cột source_pdf_path)


# =========================================================================
# DATABASE HELPERS
# =========================================================================

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def get_or_create_book(conn, title, slug, source_pdf_path, total_pages):
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT id FROM books WHERE slug = %s", (slug,))
    row = cursor.fetchone()

    if row:
        book_id = row["id"]
        # Cập nhật lại total_pages/source_pdf_path phòng khi có thay đổi
        cursor.execute(
            """
            UPDATE books
            SET title = %s, source_pdf_path = %s, total_pages = %s, updated_at = NOW()
            WHERE id = %s
            """,
            (title, source_pdf_path, total_pages, book_id),
        )
        conn.commit()
        cursor.close()
        print(f"-> Sách '{title}' đã tồn tại (book_id={book_id}), đã cập nhật total_pages={total_pages}")
        return book_id

    cursor.execute(
        """
        INSERT INTO books (title, slug, source_pdf_path, total_pages, created_at, updated_at)
        VALUES (%s, %s, %s, %s, NOW(), NOW())
        """,
        (title, slug, source_pdf_path, total_pages),
    )
    conn.commit()
    book_id = cursor.lastrowid
    cursor.close()
    print(f"-> Đã tạo mới book_id={book_id} cho sách '{title}' ({total_pages} trang)")
    return book_id


def count_page_files(pages_dir):
    return len(glob.glob(os.path.join(pages_dir, "page-*.pdf")))


# =========================================================================
# MAIN
# =========================================================================

def main():
    try:
        conn = get_connection()
    except MySQLError as e:
        print(f"❌ Không kết nối được MySQL: {e}")
        return

    total_pages = count_page_files(PAGES_DIR)
    if total_pages == 0:
        print(f"⚠️  Không tìm thấy file page-*.pdf nào trong: {PAGES_DIR}")

    book_id = get_or_create_book(
        conn,
        title=BOOK_TITLE,
        slug=BOOK_SLUG,
        source_pdf_path=SOURCE_PDF_PATH,
        total_pages=total_pages,
    )

    conn.close()
    print("=" * 60)
    print(f"🎉 HOÀN TẤT - book_id = {book_id}, slug = '{BOOK_SLUG}'")
    print("   -> Dùng đúng BOOK_SLUG này trong file 2 và file 3.")


if __name__ == "__main__":
    main()