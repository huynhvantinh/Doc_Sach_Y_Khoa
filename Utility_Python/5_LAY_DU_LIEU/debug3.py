import json

with open("page-0002_raw.json", encoding="utf-8") as f:
    data = json.load(f)

print("Số ảnh raster thật:", len(data["images"]))
print("Số vùng vector có tô màu:", len(data["fill_drawings"]))
print("Số text block:", len([b for b in data["blocks"] if b["type"] == 0]))