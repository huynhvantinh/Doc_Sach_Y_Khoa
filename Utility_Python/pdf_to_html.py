import fitz  # pip install PyMuPDF


input_pdf = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/page-0027.pdf" ##D:\00000_Y_DA_KHOA\SACH_PDF_FOR_WEB_DOC_SACH
output_pdf = "/mnt/d/00000_Y_DA_KHOA/SACH_PDF_FOR_WEB_DOC_SACH/out.html"
doc = fitz.open(input_pdf)
html_parts = []

for page in doc:
    html_parts.append(page.get_text("html"))

full_html = "<html><body>" + "".join(html_parts) + "</body></html>"

with open(output_pdf, "w", encoding="utf-8") as f:
    f.write(full_html)