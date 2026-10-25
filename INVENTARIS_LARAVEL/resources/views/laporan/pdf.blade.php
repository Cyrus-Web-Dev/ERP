<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Inventaris</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 10px; color: #1e293b; }
        h1 { text-align: center; color: #0F172A; border-bottom: 3px solid #667eea; padding-bottom: 10px; font-size: 20px; }
        .subtitle { text-align: center; color: #64748B; margin-bottom: 15px; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; font-size: 10px; }
        th { background: #0F172A; color: white; padding: 6px; text-align: left; border: 1px solid #333; }
        td { padding: 5px 6px; border: 1px solid #ddd; }
        tr:nth-child(even) { background: #f8fafc; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 10px; font-size: 8px; font-weight: bold; color: white; }
        .badge-elektronik { background: #4F46E5; }
        .badge-non-elektronik { background: #10B981; }
        .badge-perabotan { background: #D97706; }
        .badge-kendaraan { background: #DC2626; }
        .badge-gedung { background: #0F766E; }
        .summary { width: 100%; margin: 12px 0; border-collapse: collapse; }
        .summary td { border: none; text-align: center; padding: 8px; background: #f1f5f9; }
        .summary .label { font-size: 9px; color: #64748B; display: block; }
        .summary .value { font-size: 12px; font-weight: bold; color: #0F172A; }
        .footer { text-align: center; margin-top: 15px; font-size: 9px; color: #94A3B8; border-top: 1px solid #ddd; padding-top: 8px; }
        tfoot td { font-weight: bold; background: #f1f5f9; }
    </style>
</head>
<body>
    <h1>LAPORAN INVENTARIS ASET</h1>
    <p class="subtitle">
        Dicetak: {{ now()->translatedFormat('d F Y, H:i') }} oleh {{ $dicetakOleh }} &nbsp;|&nbsp;
        Total Item: {{ number_format($gabungan->count()) }}
    </p>

    <table class="summary">
        <tr>
            <td><span class="label">Total Item</span><span class="value">{{ number_format($gabungan->count()) }}</span></td>
            <td><span class="label">Total Nilai</span><span class="value">Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}</span></td>
            @foreach($totalPerSumber as $s => $total)
                <td><span class="label">{{ $s }}</span><span class="value">Rp {{ number_format($total, 0, ',', '.') }}</span></td>
            @endforeach
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Sumber</th>
                <th>Nama</th>
                <th>Kategori/Merk</th>
                <th class="text-right">Jumlah</th>
                <th class="text-right">Harga</th>
                <th class="text-right">Total</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($gabungan as $row)
                @php
                    $badgeClass = 'badge-'.strtolower(str_replace(' ', '-', $row['sumber']));
                @endphp
                <tr>
                    <td><span class="badge {{ $badgeClass }}">{{ $row['sumber'] }}</span></td>
                    <td>{{ $row['nama'] }}</td>
                    <td>{{ $row['kategori'] }}</td>
                    <td class="text-right">{{ $row['jumlah'] }}</td>
                    <td class="text-right">Rp {{ number_format($row['harga'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($row['total'], 0, ',', '.') }}</td>
                    <td>{{ $row['tanggal']?->format('d/m/Y') ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" class="text-right">TOTAL KESELURUHAN</td>
                <td class="text-right">Rp {{ number_format($totalKeseluruhan, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p class="footer">Sistem Inventaris Aset Perusahaan &copy; {{ now()->year }}</p>
</body>
</html>
