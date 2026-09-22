{{-- <!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đọc Sách Y Khoa - Màn hình 2K</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: #1e1e1e;
        }
        /* Khung chứa PDF tràn 100% viewport */
        #pdf-container {
            width: 100vw;
            height: 100vh;
            border: none;
        }
    </style>
</head>
<body>

    <!-- Sử dụng trình xem PDF.js Viewer chính chủ thông qua CDN CDNJS -->
    <!-- Tự động thêm tham số page & zoom fit chiều rộng/chiều cao để tối ưu cho khổ đôi A4 -->
    <iframe 
        id="pdf-container" 
        src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/web/viewer.html?file=/pdf-stream#page=1&zoom=page-fit" 
        allowfullscreen>
    </iframe>

</body>
</html> --}}


<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đọc Sách Y Khoa - Màn hình 2K</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background-color: #525659; /* Màu nền xám chuẩn của trình xem PDF */
        }
        /* Ép khung chứa PDF tràn toàn bộ màn hình 2K */
        embed, iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>

    <!-- Nhúng trực tiếp đường dẫn stream PDF -->
    <!-- #toolbar=1: Hiện thanh công cụ (chuyển trang, zoom, in) -->
    <!-- #view=FitH: Tự động zoom vừa chiều rộng màn hình (Cực kỳ tối ưu cho trang A4 đôi) -->
    <embed 
        src="/pdf-stream#toolbar=1&view=FitH" 
        type="application/pdf">

</body>
</html>