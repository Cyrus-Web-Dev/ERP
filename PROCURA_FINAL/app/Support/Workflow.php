<?php

namespace App\Support;

/**
 * Sumber tunggal label & warna status. Dikirim ke Vue lewat HandleInertiaRequests ($page.props.workflow)
 * dan dipakai juga oleh email + REST API, sehingga istilahnya sama di mana-mana.
 */
class Workflow
{
    /** Urutan "stasiun" jalur pengadaan (dipakai komponen RouteTracker). */
    public const STATIONS = [
        'submitted' => 'Diminta',
        'reviewing' => 'Ditinjau',
        'sourcing' => 'Penawaran',
        'funding' => 'Dana',
        'ordered' => 'Dipesan',
        'received' => 'Diterima',
        'handed_over' => 'Diserahkan',
    ];

    public const REQUEST_STATUS = [
        'submitted' => ['Baru masuk', 'sky'],
        'reviewing' => ['Ditinjau', 'indigo'],
        'sourcing' => ['Cari penawaran', 'violet'],
        'funding' => ['Menunggu dana', 'amber'],
        'funded' => ['Dana cair', 'teal'],
        'ordered' => ['Dipesan ke vendor', 'blue'],
        'received' => ['Barang diterima', 'cyan'],
        'handed_over' => ['Diserahkan', 'green'],
        'completed' => ['Selesai', 'green'],
        'rejected' => ['Ditolak', 'rose'],
        'cancelled' => ['Dibatalkan', 'slate'],
    ];

    public const FUNDING_STATUS = [
        'pending_internal' => ['Menunggu persetujuan internal', 'amber'],
        'submitted' => ['Menunggu keputusan Keuangan', 'sky'],
        'approved' => ['Disetujui, belum cair', 'teal'],
        'revision' => ['Diminta revisi', 'violet'],
        'rejected' => ['Ditolak Keuangan', 'rose'],
        'disbursed' => ['Dana cair', 'green'],
        'settled' => ['LPJ selesai', 'slate'],
        'cancelled' => ['Dibatalkan', 'slate'],
    ];

    public const PO_STATUS = [
        'draft' => ['Draf', 'slate'],
        'sent' => ['Terkirim ke vendor', 'blue'],
        'partial' => ['Diterima sebagian', 'amber'],
        'received' => ['Diterima penuh', 'green'],
        'cancelled' => ['Dibatalkan', 'rose'],
    ];

    public const PRIORITY = [
        'low' => ['Rendah', 'slate'],
        'normal' => ['Normal', 'sky'],
        'high' => ['Tinggi', 'amber'],
        'urgent' => ['Mendesak', 'rose'],
    ];

    public const REPORT_KIND = [
        'request' => 'Permintaan barang/jasa',
        'status_update' => 'Kabar perkembangan',
        'handover' => 'Serah terima',
        'funding_request' => 'Pengajuan dana',
        'funding_decision' => 'Keputusan dana',
        'disbursement' => 'Pencairan dana',
        'settlement' => 'Pertanggungjawaban dana (LPJ)',
        'note' => 'Catatan',
    ];

    public const WEBHOOK_EVENTS = [
        'request.status_changed' => 'Status permintaan berubah',
        'funding.submitted' => 'Pengajuan dana dikirim ke Keuangan',
        'funding.settled' => 'LPJ dana diserahkan',
        'purchase_order.sent' => 'PO dikirim ke vendor',
        'goods.received' => 'Barang/jasa diterima',
        'handover.created' => 'Serah terima ke Inventaris',
    ];

    public const API_SCOPES = [
        'requests:write' => 'Membuat permintaan barang/jasa (Inventaris)',
        'requests:read' => 'Membaca status permintaan',
        'funding:read' => 'Membaca pengajuan dana (Keuangan)',
        'funding:decide' => 'Mengirim keputusan & pencairan dana (Keuangan)',
        'handover:confirm' => 'Mengonfirmasi serah terima (Inventaris)',
        'catalog:read' => 'Membaca katalog barang',
    ];

    public static function payload(): array
    {
        $map = fn (array $a) => collect($a)->map(fn ($v) => ['label' => $v[0], 'color' => $v[1]])->all();

        return [
            'request' => $map(self::REQUEST_STATUS),
            'funding' => $map(self::FUNDING_STATUS),
            'po' => $map(self::PO_STATUS),
            'priority' => $map(self::PRIORITY),
            'stations' => self::STATIONS,
            'reportKinds' => self::REPORT_KIND,
            'categories' => config('procura.categories'),
        ];
    }

    public static function label(array $set, string $key): string
    {
        $v = $set[$key] ?? null;

        return is_array($v) ? $v[0] : ($v ?? $key);
    }
}
