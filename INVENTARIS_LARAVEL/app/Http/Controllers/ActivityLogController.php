<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $module = $request->query('module', '');
        $username = $request->query('username', '');
        $tglDari = $request->query('tgl_dari', '');
        $tglSampai = $request->query('tgl_sampai', '');

        $query = ActivityLog::query();

        if ($module !== '') {
            $query->where('module', $module);
        }
        if ($username !== '') {
            $query->where('username', $username);
        }
        if ($tglDari !== '') {
            $query->whereDate('created_at', '>=', $tglDari);
        }
        if ($tglSampai !== '') {
            $query->whereDate('created_at', '<=', $tglSampai);
        }

        $logs = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        $moduleList = ActivityLog::select('module')->distinct()->orderBy('module')->pluck('module');
        $usernameList = ActivityLog::select('username')->distinct()->whereNotNull('username')->orderBy('username')->pluck('username');

        return view('log-aktivitas.index', [
            'logs' => $logs,
            'moduleList' => $moduleList,
            'usernameList' => $usernameList,
            'filter' => compact('module', 'username', 'tglDari', 'tglSampai'),
        ]);
    }
}
