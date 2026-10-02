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
import xml.etree.ElementTree as ET #Để xuát file svg không text

# =========================================================================
# CONFIG - đổi các giá trị này khi làm sách khác
# =========================================================================

BASE_DIR = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/"

# BOOK_SLUG = "grays-anatomy-4th"  # phải khớp với BOOK_SLUG đã tạo ở file 1
# PAGES_DIR = os.path.join(BASE_DIR, "1_Grays_Anatomy_Students_pages") # Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
# JSONS_DIR = os.path.join(BASE_DIR, "1_Grays_Anatomy_Students_jsons") # Thư mục lưu file raw_json output
# IMAGES_DIR = os.path.join(BASE_DIR, "1_Grays_Anatomy_Students_images") # Thư mục lưu file ảnh thật trích ra từ PDF (đã convert sang RGB để hiển thị web)

BOOK_SLUG = "test-tong-hop"  # phải khớp với BOOK_SLUG đã tạo ở file 1
PAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_pages") # Thư mục chứa các file page-0001.pdf, page-0002.pdf, ...
JSONS_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_jsons") # Thư mục lưu file raw_json output
IMAGES_DIR = os.path.join(BASE_DIR, "0_Test_Tong_Hop_images") # Thư mục lưu file ảnh thật trích ra từ PDF (đã convert sang RGB để hiển thị web)



DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3306,
    "user": "root",
    "password": "",
    "database": "doc_sach_y_khoa",
}



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
    Trích ảnh THẬT từ trang.
    - Ảnh có xref (XObject thật): giữ NGUYÊN VẸN bytes gốc + extension gốc,
      trích qua doc.extract_image(xref) - không giải nén lại, không convert màu.
    - Ảnh KHÔNG có xref (xref=0, thường là inline image trong content stream):
      không thể trích bytes gốc vì không có object PDF độc lập để trỏ tới.
      Fallback: render lại đúng vùng bbox đó từ trang bằng get_pixmap(clip=...),
      đánh dấu rõ "extracted_via": "render_fallback" để phân biệt với bytes gốc thật.
    - Ảnh có bbox rỗng (toàn số 0, width/height=0) là placeholder không có nội
      dung thực - bỏ qua, không cố render.
    - Tên file dựa theo field "number" (số thứ tự PyMuPDF gán cho ảnh đó trong
      PDF), KHÔNG dùng idx vòng lặp - để tên file luôn khớp đúng với "number"
      ghi trong JSON, kể cả khi có ảnh bị bỏ qua ở giữa danh sách.

    Lưu ý: ảnh hệ màu CMYK trích qua extract_image() lưu ra sẽ KHÔNG hiển thị
    đúng màu nếu render thẳng trên trình duyệt. Việc convert sang RGB (nếu cần)
    nên làm ở bước xử lý (file 3) hoặc lúc serve ảnh bên Laravel, không làm ở đây.
    """
    os.makedirs(IMAGES_DIR, exist_ok=True)
    image_info_list = page.get_image_info(xrefs=True, hashes=False)

    # Một số phiên bản PyMuPDF vẫn trả field "digest" (bytes, MD5 hash của ảnh)
    # dù đã truyền hashes=False - xóa thủ công để chắc chắn không dính bytes vào JSON.
    # for info in image_info_list:
        # info.pop("digest", None)

    for info in image_info_list:
        # 1. Xóa an toàn field 'digest' (chứa bytes) nếu tồn tại
        # info.pop("digest", None)

        # 2. Hoặc nếu bạn muốn chuyển digest sang hex (trường hợp hashes=True):
        if isinstance(info.get("digest"), bytes):
            info["digest"] = info["digest"].hex()
            
        number = info.get("number")
        xref = info.get("xref")
        bbox = info.get("bbox")

        is_empty_bbox = (
            not bbox
            or (bbox[0] == bbox[1] == bbox[2] == bbox[3] == 0)
            or bbox[2] <= bbox[0]
            or bbox[3] <= bbox[1]
        )

        if is_empty_bbox:
            info["image_ext"] = None
            continue

        if xref:
            # Ảnh XObject thật - trích bytes gốc, giữ nguyên vẹn
            try:
                base_image = doc.extract_image(xref)  # bytes gốc, không giải nén/nén lại
                image_bytes = base_image["image"]
                ext = base_image["ext"]  # vd: "jpeg", "png", "jp2", "jpx"...

                filename = f"page-{page_number:04d}_img{number}.{ext}"
                image_path = os.path.join(IMAGES_DIR, filename)  # chỉ dùng để ghi file, KHÔNG lưu vào JSON

                with open(image_path, "wb") as f:
                    f.write(image_bytes)

                info["image_ext"] = ext

            except Exception as e:
                info["image_ext"] = None
                info["extract_error"] = str(e)

        else:
            # xref = 0 (thường là inline image) - không trích được bytes gốc,
            # fallback: render lại đúng vùng bbox từ trang
            info["image_ext"] = None
            # Nếu không muốn lưu những ảnh này thì khóa những dòng dưới lại:
            # try:
            #     clip_rect = fitz.Rect(bbox)
            #     pix = page.get_pixmap(clip=clip_rect, dpi=150)

            #     filename = f"page-{page_number:04d}_img{number}.png"
            #     image_path = os.path.join(IMAGES_DIR, filename)
            #     pix.save(image_path)
            #     pix = None

            #     info["image_ext"] = "png"
            #     info["extracted_via"] = "render_fallback"  # KHÔNG phải bytes gốc thật

            # except Exception as e:
            #     info["image_ext"] = None
            #     info["extract_error"] = str(e)

    return image_info_list


def serialize_drawing_items(items):
    """
    Chuyển các lệnh vẽ path (line/curve/rect/quad) của PyMuPDF thành dữ liệu
    JSON serialize được - giữ đúng tọa độ từng đoạn, không chỉ bounding box,
    vì đường kẻ chéo (leader line) cần biết chính xác 2 đầu mút mới vẽ lại đúng.
    """
    result = []
    for item in items:
        cmd = item[0]
        if cmd == "l":  # đường thẳng: điểm đầu -> điểm cuối
            p1, p2 = item[1], item[2]
            result.append({"cmd": "l", "points": [[p1.x, p1.y], [p2.x, p2.y]]})
        elif cmd == "c":  # đường cong Bezier: 4 điểm điều khiển
            pts = item[1:5]
            result.append({"cmd": "c", "points": [[p.x, p.y] for p in pts]})
        elif cmd == "re":  # hình chữ nhật
            r = item[1]
            result.append({"cmd": "re", "rect": list(r)})
        elif cmd == "qu":  # tứ giác bất kỳ
            q = item[1]
            result.append({
                "cmd": "qu",
                "points": [[q.ul.x, q.ul.y], [q.ur.x, q.ur.y], [q.lr.x, q.lr.y], [q.ll.x, q.ll.y]],
            })
    return result


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

    ###################################### 1. LẤY TEXT
    # page_dict = page.get_text("dict", flags=fitz.TEXT_DEHYPHENATE) #TEXT_DEHYPHENATE thì bị sai ở những dòng có dấu nối vì xuống hàng
    page_dict = page.get_text("dict")

    # Block type=1 (image block) của get_text("dict") có kèm field "image" chứa
    # RAW BYTES nhị phân của ảnh -> không serialize được ra JSON, và cũng dư thừa
    # vì ảnh thật đã được trích riêng qua extract_images_with_files() ở dưới.
    # Xóa field "image" (bytes) khỏi các block này, chỉ giữ lại bbox/thông tin vị trí.
    for block in page_dict.get("blocks", []):
        if block.get("type") == 1 and "image" in block:
            # nếu không xóa block["image"] hoặc gán lại thì sẽ bị lỗi "type bytes is not JSON serializable", vì block["image"] có type kiểu byte
            # Kiểm tra nếu đúng là kiểu bytes thì mới xóa hoặc convert sang hex string
            if isinstance(block["image"], bytes):
                del block["image"] # Ưu tiên xóa
                # block["image"] = block["image"].hex() # Lưu vào thì chuỗi hex là toàn bộ file ảnh nên rất nặng


    ###################################### 2. LẤY IMAGE
    image_info = extract_images_with_files(page, doc, page_number)


    ###################################### 3. LẤY DRAW dạng FILL và dạng LINE
    drawings = page.get_drawings()
    
    fill_drawings = [
        {
            "rect": list(d["rect"]) if d.get("rect") else None,
            "fill": d.get("fill"),
            "stroke": d.get("color"),
        }
        # d
        for d in drawings
        if d.get("fill") is not None
    ]

    # Đường kẻ nối từ label vào hình (leader line) thường là path CHỈ CÓ VIỀN
    # (stroke), KHÔNG tô nền (fill=None) - nhóm này bị fill_drawings ở trên bỏ
    # sót hoàn toàn. Lưu riêng, giữ đúng tọa độ 2 đầu mút từng đoạn để có thể
    # vẽ lại chính xác đường kẻ này khi hiển thị (không chỉ bounding box).
    line_drawings = [
        {
            "rect": list(d["rect"]) if d.get("rect") else None,
            "color": d.get("color"),
            "width": d.get("width"),
            "items": serialize_drawing_items(d.get("items", [])),
        }
        # d
        for d in drawings
        if d.get("fill") is None and d.get("color") is not None
    ]


    ###################################### 4. LẤY ANNOTATIONS
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
        "line_drawings": line_drawings,
        "highlight_annots": highlight_annots,
    }
    
    # ///////////////////////// Lưu file PDF dưới dạng svg
    # Lưu toàn bộ file PDF dưới dạng svg
    svg_code = page.get_svg_image()
    with open("svg_full.svg", "w", encoding="utf-8") as f:
        f.write(svg_code)
    print("Đã xuất file SVG Full!")
        
    # Tạo redaction cho toàn bộ vùng text, chỉ xóa text, giữ nguyên ảnh + vector
    page.add_redact_annot(page.rect)
    page.apply_redactions(
        images=fitz.PDF_REDACT_IMAGE_NONE,          # giữ ảnh
        graphics=fitz.PDF_REDACT_LINE_ART_NONE,     # giữ đường vẽ/vector
        text=fitz.PDF_REDACT_TEXT_REMOVE,           # xóa text
    )
    svg = page.get_svg_image()  # text_as_path mặc định, nhưng giờ không còn text nào
    with open("svg_without_text.svg", "w", encoding="utf-8") as f:
        f.write(svg)
        
    print("Đã xuất file SVG không có text!")
    # /////////////////////////

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
        TEST_SINGLE_PAGE = 7 #Dùng khi muốn test 1 page bất kì
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
        # if (
        #     SKIP_IF_ALREADY_EXTRACTED
        #     and existing
        #     and existing["status"] not in ("pending", "failed")
        # ):
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