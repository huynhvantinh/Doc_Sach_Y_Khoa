import json
import fitz

doc = fitz.open("page-0001.pdf")
doc = fitz.open("page-0011.pdf")
page = doc[0]

page_dict = page.get_text("dict", flags=fitz.TEXT_DEHYPHENATE)
image_info = page.get_image_info(xrefs=True, hashes=False)
drawings = page.get_drawings()
annots = list(page.annots() or [])

def test_dump(name, data):
    try:
        json.dumps(data, default=str)  # default=str chỉ để không crash, chỉ để xem field nào lỗi thật
        print(f"✅ {name}: OK")
    except TypeError as e:
        print(f"❌ {name}: {e}")

test_dump("blocks", page_dict.get("blocks"))
test_dump("image_info (raw)", image_info)
test_dump("drawings (raw)", drawings)
for a in annots:
    test_dump(f"annot {a} colors", a.colors)