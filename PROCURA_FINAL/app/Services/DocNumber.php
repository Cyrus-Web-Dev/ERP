<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Nomor dokumen berurutan per bulan, mis. PO-2609-0007. Aman untuk permintaan bersamaan (baris dikunci). */
class DocNumber
{
    public static function next(string $prefix): string
    {
        $period = now()->format('ym');

        return DB::transaction(function () use ($prefix, $period) {
            $row = DB::table('document_counters')->where(['prefix' => $prefix, 'period' => $period])->lockForUpdate()->first();

            if (! $row) {
                DB::table('document_counters')->insert(['prefix' => $prefix, 'period' => $period, 'last' => 1]);
                $n = 1;
            } else {
                $n = $row->last + 1;
                DB::table('document_counters')->where('id', $row->id)->update(['last' => $n]);
            }

            return sprintf('%s-%s-%04d', $prefix, $period, $n);
        });
    }
}
