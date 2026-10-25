<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogAktivitasMiddleware
{
    /**
     * Middleware ini disiapkan sebagai tempat hook global kalau nanti
     * diperlukan (mis. mencatat kunjungan halaman). Untuk pencatatan
     * CRUD (tambah/ubah/hapus), kita pakai App\Models\ActivityLog::catat()
     * langsung di masing-masing Controller supaya pesannya lebih spesifik
     * (sama seperti autoLog() di project lama).
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }
}
