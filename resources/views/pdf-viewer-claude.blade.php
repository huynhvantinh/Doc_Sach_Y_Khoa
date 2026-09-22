<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: #525659;
        }
        embed {
            width: 100vw;
            height: 100vh;
            display: block;
            border: none;
        }
    </style>
</head>
<body>
    {{-- FitH = fit theo chiều ngang, hợp lý vì trang có thể rất rộng
         (2 tờ A4 Anh-Việt gộp đôi) --}}
    <embed src="{{ $pdfUrl }}#view=FitH&toolbar=1&navpanes=0"
           type="application/pdf">
</body>
</html>