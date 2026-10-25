<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $query = User::where('approved', 1);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('nama_lengkap')->paginate(10)->withQueryString();

        return view('akun.index', ['users' => $users, 'search' => $search]);
    }

    public function updateStatus(Request $request, User $user)
    {
        if ($user->id_user === $request->user()->id_user) {
            return back()->with('error', 'Anda tidak bisa menonaktifkan akun sendiri.');
        }

        $data = $request->validate(['status' => ['required', Rule::in(['aktif', 'nonaktif'])]]);

        $user->update($data);

        ActivityLog::catat('users', 'UPDATE', 'Ubah Status Akun', "Mengubah status {$user->username} menjadi {$data['status']}");

        return back()->with('success', "Status akun {$user->username} berhasil diubah menjadi \"{$data['status']}\".");
    }

    public function updateRole(Request $request, User $user)
    {
        if ($user->id_user === $request->user()->id_user) {
            return back()->with('error', 'Anda tidak bisa mengubah role sendiri.');
        }

        $data = $request->validate(['level_akses' => ['required', Rule::in(['super_admin', 'admin'])]]);

        // Kalau ini satu-satunya super_admin, jangan izinkan diturunkan jadi admin biasa.
        if ($user->level_akses === 'super_admin' && $data['level_akses'] === 'admin') {
            $jumlahSuperAdmin = User::where('level_akses', 'super_admin')->where('approved', 1)->count();
            if ($jumlahSuperAdmin <= 1) {
                return back()->with('error', 'Tidak bisa mengubah role: ini adalah Super Admin terakhir.');
            }
        }

        $user->update([
            'level_akses' => $data['level_akses'],
            'id_role' => $data['level_akses'] === 'super_admin' ? 1 : 2,
        ]);

        ActivityLog::catat('users', 'UPDATE', 'Ubah Role Akun', "Mengubah role {$user->username} menjadi {$data['level_akses']}");

        return back()->with('success', "Role akun {$user->username} berhasil diubah.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id_user === $request->user()->id_user) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }

        if ($user->level_akses === 'super_admin') {
            $jumlahSuperAdmin = User::where('level_akses', 'super_admin')->where('approved', 1)->count();
            if ($jumlahSuperAdmin <= 1) {
                return back()->with('error', 'Tidak bisa menghapus: ini adalah Super Admin terakhir.');
            }
        }

        $username = $user->username;
        $user->delete();

        ActivityLog::catat('users', 'DELETE', 'Hapus Akun', "Menghapus akun {$username}");

        return back()->with('success', "Akun {$username} berhasil dihapus.");
    }
}
