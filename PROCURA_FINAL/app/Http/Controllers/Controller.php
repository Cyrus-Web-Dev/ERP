<?php

namespace App\Http\Controllers;

use App\Exceptions\WorkflowException;
use Closure;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    /**
     * Jalankan aksi alur kerja; bila aturan pengadaan dilanggar, kembali dengan pesan yang jelas
     * (ditampilkan sebagai banner di AppLayout lewat kunci error "general").
     */
    protected function guard(Closure $action, ?string $success = null)
    {
        try {
            $result = $action();
        } catch (WorkflowException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        if ($result instanceof \Symfony\Component\HttpFoundation\Response) {
            return $result;
        }

        return back()->with('success', $success ?? 'Berhasil disimpan.');
    }
}
