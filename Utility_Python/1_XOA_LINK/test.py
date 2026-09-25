import fitz

doc = fitz.open("/mnt/d/00000_Y_SI_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/Grays_Anatomy_CLEANED_p1-20.pdf")
PAGE_INDEX = 3   # <-- đổi thành trang chứa khung ma, đếm từ 0

page = doc[PAGE_INDEX]

print("----- DANH SÁCH XOBJECT HỢP LỆ (get_xobjects) -----")
for x in page.get_xobjects():
    print(x)

print("\n----- DANH SÁCH ẢNH (get_images) -----")
for i in page.get_images(full=True):
    print(i)

print("\n----- RAW CONTENT STREAM (giới hạn 4000 ký tự đầu) -----")
for cxref in page.get_contents():
    raw = doc.xref_stream(cxref)
    print(f"--- content xref {cxref} ---")
    print(raw.decode("latin-1")[:4000])

doc.close()