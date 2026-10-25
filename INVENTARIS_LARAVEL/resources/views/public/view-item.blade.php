<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judul }} - Sistem Inventaris</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;}</style>
</head>
<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

    <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="h-48 bg-slate-200">
            @if($fotoUrl)
                <img src="{{ $fotoUrl }}" class="w-full h-full object-cover">
            @else
                <div class="w-full h-full flex items-center justify-center text-slate-400"><i class="bi bi-box-seam text-5xl"></i></div>
            @endif
        </div>

        <div class="p-6">
            <span class="text-xs px-2.5 py-1 rounded-full bg-brand-50 text-brand-700 font-medium">{{ $label }}</span>
            <h1 class="text-lg font-bold text-slate-800 mt-2">{{ $judul }}</h1>
            @if($subjudul)
                <p class="text-sm text-slate-500">{{ $subjudul }}</p>
            @endif

            @if(count($detail))
                <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                    @foreach($detail as $k => $v)
                        <div class="flex justify-between text-sm">
                            <span class="text-slate-500">{{ $k }}</span>
                            <span class="text-slate-800 font-medium">{{ $v }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="text-xs text-slate-400 mt-6 text-center">
                <i class="bi bi-qr-code"></i> Halaman ini terbuka dari scan label QR aset perusahaan.
            </p>
        </div>
    </div>
</body>
</html>
