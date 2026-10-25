<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{{ $heading }}</title>
</head>
<body style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background:#f8fafc;">
    <div style="background: #0F172A; color: #fff; padding: 15px; border-radius: 8px 8px 0 0; text-align: center;">
        <h1 style="margin: 0; font-size: 22px;">Sistem Inventaris Aset</h1>
    </div>
    <div style="background:#fff; border: 1px solid #E2E8F0; border-top: none; padding: 24px; border-radius: 0 0 8px 8px;">
        <h2 style="color: {{ $headingColor }}; margin-top:0;">{{ $heading }}</h2>
        {!! $bodyHtml !!}
        <hr style="border:none;border-top:1px solid #E2E8F0;margin:24px 0 12px;">
        <p style="font-size: 12px; color: #6B7280; margin:0;">Email ini dikirim otomatis oleh Sistem Inventaris Aset Perusahaan.</p>
    </div>
</body>
</html>
