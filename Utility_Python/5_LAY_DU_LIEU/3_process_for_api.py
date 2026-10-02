"""
FILE 3: Đọc `raw_json` từ DB, xử lý thành `send_api_json` - dữ liệu sẵn sàng gửi API dịch.

Công việc xử lý ở đây:
- Giải mã flags của span -> bold/italic
- Nhận diện phần tô nền (highlight) bằng cách so khớp bbox span với fill_drawings
- Convert style -> markup nhẹ: **bold**, _italic_, ==highlight==
- Phân loại mỗi block: "paragraph" (đoạn văn thường) hay "label" (nhãn ngắn nằm
  trong/gần vùng ảnh minh họa) dựa theo độ dài text + vị trí so với bbox ảnh
- Gom label theo từng ảnh (figure), gán id p1,p2... cho paragraph và l1,l2... cho label

Yêu cầu cài đặt:
    pip install mysql-connector-python --break-system-packages

Cách chạy (sau khi đã chạy file 1 và file 2):
    python3 3_process_for_api.py
"""

import os
import json
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

BOOK_SLUG = "grays-anatomy"  # phải khớp với BOOK_SLUG đã tạo ở file 1

BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

# Thư mục lưu file send_api_json output
SEND_API_JSONS_DIR = os.path.join(BASE_DIR, "Grays_Anatomy_(Annas_Archive)_send_api_jsons")

# Nếu True: bỏ qua trang đã processed/translating/translated/reviewed (chỉ xử lý status='extracted')
SKIP_IF_ALREADY_PROCESSED = True

# Ngưỡng phân loại "label" (nhãn ngắn trong hình) vs "paragraph" (đoạn văn thường)
LABEL_MAX_CHARS = 40           # text dài hơn số ký tự này -> chắc chắn là đoạn văn, không phải label
IMAGE_LABEL_MARGIN = 80        # (points) mở rộng bbox ảnh thêm bao nhiêu để "bắt" các label xung quanh
HIGHLIGHT_OVERLAP_THRESHOLD = 0.5  # tỷ lệ diện tích span nằm trong vùng tô màu để tính là highlight


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


def get_pages_to_process(conn, book_id):
    cursor = conn.cursor(dictionary=True)
    if SKIP_IF_ALREADY_PROCESSED:
        cursor.execute(
            "SELECT id, page_number, raw_json FROM pages WHERE book_id = %s AND status = 'extracted' ORDER BY page_number",
            (book_id,),
        )
    else:
        cursor.execute(
            "SELECT id, page_number, raw_json FROM pages WHERE book_id = %s AND raw_json IS NOT NULL ORDER BY page_number",
            (book_id,),
        )
    rows = cursor.fetchall()
    cursor.close()
    return rows


def update_send_api_json(conn, page_id, send_api_data, status="processed", error_message=None):
    send_api_str = json.dumps(send_api_data, ensure_ascii=False) if send_api_data is not None else None
    cursor = conn.cursor()
    cursor.execute(
        """
        UPDATE pages
        SET send_api_json = %s, status = %s, error_message = %s, updated_at = NOW()
        WHERE id = %s
        """,
        (send_api_str, status, error_message, page_id),
    )
    conn.commit()
    cursor.close()


# =========================================================================
# XỬ LÝ STYLE (bold/italic/highlight)
# =========================================================================

def flags_to_style(flags):
    """Giải mã bitmask flags của PyMuPDF span."""
    bold = bool(flags & 2 ** 4)
    italic = bool(flags & 2 ** 1)
    return bold, italic


def rect_area(rect):
    x0, y0, x1, y1 = rect
    return max(0, x1 - x0) * max(0, y1 - y0)


def rect_overlap_ratio(rect_a, rect_b):
    """Tỷ lệ diện tích rect_a nằm trong rect_b (0.0 - 1.0)."""
    ax0, ay0, ax1, ay1 = rect_a
    bx0, by0, bx1, by1 = rect_b

    ix0, iy0 = max(ax0, bx0), max(ay0, by0)
    ix1, iy1 = min(ax1, bx1), min(ay1, by1)

    inter_area = max(0, ix1 - ix0) * max(0, iy1 - iy0)
    a_area = rect_area(rect_a)
    if a_area == 0:
        return 0.0
    return inter_area / a_area


def is_highlighted(span_bbox, fill_drawings):
    for d in fill_drawings:
        if not d.get("rect"):
            continue
        if rect_overlap_ratio(span_bbox, d["rect"]) >= HIGHLIGHT_OVERLAP_THRESHOLD:
            return True
    return False


def wrap_markup(text, bold, italic, highlighted):
    if not text:
        return text
    if bold:
        text = f"**{text}**"
    if italic:
        text = f"_{text}_"
    if highlighted:
        text = f"=={text}=="
    return text


# =========================================================================
# PHÂN LOẠI BLOCK: paragraph vs label
# =========================================================================

def expand_rect(rect, margin):
    x0, y0, x1, y1 = rect
    return [x0 - margin, y0 - margin, x1 + margin, y1 + margin]


def rect_center(rect):
    x0, y0, x1, y1 = rect
    return ((x0 + x1) / 2, (y0 + y1) / 2)


def point_in_rect(point, rect):
    px, py = point
    x0, y0, x1, y1 = rect
    return x0 <= px <= x1 and y0 <= py <= y1


def build_block_markup(block, fill_drawings):
    """Ghép toàn bộ span trong block thành 1 chuỗi markup + text thuần (để tính độ dài)."""
    markup_parts = []
    plain_parts = []

    for line in block.get("lines", []):
        line_markup = []
        for span in line.get("spans", []):
            text = span.get("text", "")
            if not text:
                continue
            bold, italic = flags_to_style(span.get("flags", 0))
            highlighted = is_highlighted(span.get("bbox", [0, 0, 0, 0]), fill_drawings)
            line_markup.append(wrap_markup(text, bold, italic, highlighted))
            plain_parts.append(text)
        markup_parts.append("".join(line_markup))

    markup = " ".join(m for m in markup_parts if m)
    plain_text = " ".join(plain_parts).strip()
    return markup, plain_text


def classify_and_build(raw_data):
    images = raw_data.get("images", [])
    fill_drawings = raw_data.get("fill_drawings", [])

    figures = []
    for idx, img in enumerate(images, start=1):
        figures.append({
            "figure_id": f"fig{idx}",
            "image_path": img.get("image_path"),
            "bbox": img.get("bbox"),
            "labels": [],
        })

    paragraphs = []
    p_counter = 1

    for block in raw_data.get("blocks", []):
        if block.get("type") != 0:  # chỉ xử lý text block (0 = text, 1 = image)
            continue

        markup, plain_text = build_block_markup(block, fill_drawings)
        if not plain_text:
            continue

        block_bbox = block.get("bbox")
        assigned_figure = None

        if block_bbox:
            center = rect_center(block_bbox)
            for fig, img in zip(figures, images):
                img_bbox = img.get("bbox")
                if not img_bbox:
                    continue
                if point_in_rect(center, expand_rect(img_bbox, IMAGE_LABEL_MARGIN)):
                    assigned_figure = fig
                    break

        is_short_enough = len(plain_text) <= LABEL_MAX_CHARS and "." not in plain_text

        if assigned_figure is not None and is_short_enough:
            label_id = f"l{len(assigned_figure['labels']) + 1}"
            assigned_figure["labels"].append({"id": label_id, "text": plain_text})
        else:
            paragraphs.append({"id": f"p{p_counter}", "markup": markup})
            p_counter += 1

    return {
        "paragraphs": paragraphs,
        "figures": figures,
    }


# =========================================================================
# MAIN
# =========================================================================

def main():
    os.makedirs(SEND_API_JSONS_DIR, exist_ok=True)

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

    pages = get_pages_to_process(conn, book_id)
    total = len(pages)
    print(f"-> book_id={book_id}, có {total} trang cần xử lý (status='extracted')")

    success_count = 0
    fail_count = 0

    for idx, row in enumerate(pages, start=1):
        page_id = row["id"]
        page_number = row["page_number"]
        raw_json_value = row["raw_json"]

        try:
            raw_data = (
                json.loads(raw_json_value) if isinstance(raw_json_value, str) else raw_json_value
            )
            if not raw_data:
                raise ValueError("raw_json rỗng hoặc NULL")

            send_api_data = classify_and_build(raw_data)

            json_filename = f"page-{page_number:04d}_send_api.json"
            json_path = os.path.join(SEND_API_JSONS_DIR, json_filename)
            with open(json_path, "w", encoding="utf-8") as f:
                json.dump(send_api_data, f, ensure_ascii=False, indent=2)

            update_send_api_json(conn, page_id, send_api_data, status="processed")
            success_count += 1

        except Exception as e:
            error_msg = str(e)
            print(f"⚠️  Lỗi ở trang {page_number}: {error_msg}")
            update_send_api_json(conn, page_id, None, status="failed", error_message=error_msg)
            fail_count += 1

        if idx % 50 == 0 or idx == total:
            print(f"   Tiến độ: {idx}/{total} (thành công: {success_count}, lỗi: {fail_count})")

    conn.close()

    print("=" * 60)
    print(f"🎉 HOÀN TẤT")
    print(f"   Tổng số trang xử lý: {total}")
    print(f"   Thành công:          {success_count}")
    print(f"   Lỗi:                 {fail_count}")


if __name__ == "__main__":
    main()