<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\GenericNotificationMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ApprovalController extends Controller
{
    public function index()
    {
        $pendingUsers = User::where('approved', 0)->orderBy('created_at')->get();
        $pendingResets = User::whereNotNull('reset_requested_at')->orderBy('reset_requested_at')->get();

        return view('admin.approval.index', compact('pendingUsers', 'pendingResets'));
    }

    public function approveUser(Request $request, User $user)
    {
        $user->forceFill([
            'approved' => 1,
            'approved_by' => $request->user()->id_user,
            'approved_at' => now(),
        ])->save();

        $roleName = $user->level_akses === 'super_admin' ? 'Super Admin' : 'Admin';

        Mail::to($user->email)->send(new GenericNotificationMail(
            subjectLine: 'Pendaftaran Anda Disetujui',
            heading: 'Pendaftaran Disetujui!',
            headingColor: '#22C55E',
            bodyHtml: '
                <p>Halo <strong>'.e($user->nama_lengkap).'</strong>,</p>
                <p>Selamat! Pendaftaran Anda sebagai <strong>'.$roleName.'</strong> telah disetujui.</p>
                <div style="background:#F0FDF4;padding:15px;border-radius:8px;margin:20px 0;">
                    <p style="margin:4px 0;">Username: <strong>'.e($user->username).'</strong></p>
                    <p style="margin:4px 0;">Level Akses: <strong>'.$roleName.'</strong></p>
                </div>
                <p>Silakan login di: <a href="'.route('login').'">'.route('login').'</a></p>
            ',
        ));

        ActivityLog::catat('users', 'approve', 'Persetujuan User', "Menyetujui pendaftaran {$user->username}");

        return back()->with('success', "User {$user->username} berhasil disetujui.");
    }

    public function rejectUser(Request $request, User $user)
    {
        $email = $user->email;
        $nama = $user->nama_lengkap;
        $username = $user->username;

        $user->delete();

        Mail::to($email)->send(new GenericNotificationMail(
            subjectLine: 'Pendaftaran Anda Ditolak',
            heading: 'Pendaftaran Ditolak',
            headingColor: '#EF4444',
            bodyHtml: '
                <p>Halo <strong>'.e($nama).'</strong>,</p>
                <p>Mohon maaf, pendaftaran Anda ditolak oleh administrator.</p>
                <p>Jika Anda membutuhkan bantuan, silakan hubungi administrator.</p>
            ',
        ));

        ActivityLog::catat('users', 'reject', 'Penolakan User', "Menolak pendaftaran {$username}");

        return back()->with('success', 'Pendaftaran user berhasil ditolak.');
    }

    /**
     * Setujui permintaan reset password: generate token, simpan hash-nya,
     * kirim link reset ke email user. Setara action=approve_reset lama.
     */
    public function approveReset(Request $request, User $user)
    {
        if (! $user->reset_requested_at) {
            return back()->with('error', 'User ini tidak memiliki permintaan reset password.');
        }

        $token = Str::random(40);

        $user->forceFill([
            'reset_token' => Hash::make($token),
            'reset_token_expiry' => now()->addHour(),
            'reset_requested_at' => null,
        ])->save();

        $resetLink = route('password.reset.form', $token);

        Mail::to($user->email)->send(new GenericNotificationMail(
            subjectLine: 'Reset Password Anda',
            heading: 'Reset Password',
            headingColor: '#0F172A',
            bodyHtml: '
                <p>Halo <strong>'.e($user->nama_lengkap).'</strong>,</p>
                <p>Permintaan reset password Anda telah <strong>disetujui</strong> oleh administrator.</p>
                <div style="text-align:center;margin:30px 0;">
                    <a href="'.e($resetLink).'" style="background:#0F172A;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:600;display:inline-block;">
                        Reset Password Sekarang
                    </a>
                </div>
                <p><strong>Penting:</strong></p>
                <ul>
                    <li>Link ini hanya berlaku <strong>1 jam</strong></li>
                    <li>Link hanya dapat digunakan <strong>satu kali</strong></li>
                </ul>
                <p style="background:#F1F5F9;padding:10px;border-radius:6px;word-break:break-all;font-size:12px;">'.e($resetLink).'</p>
            ',
        ));

        ActivityLog::catat('users', 'approve_reset', 'Persetujuan Reset Password', "Menyetujui reset password untuk {$user->username}");

        return back()->with('success', 'Link reset password telah dikirim ke email user.');
    }

    public function rejectReset(Request $request, User $user)
    {
        $message = trim((string) $request->input('message', ''));

        $user->forceFill(['reset_requested_at' => null])->save();

        Mail::to($user->email)->send(new GenericNotificationMail(
            subjectLine: 'Permintaan Reset Password Ditolak',
            heading: 'Permintaan Reset Password Ditolak',
            headingColor: '#EF4444',
            bodyHtml: '
                <p>Halo <strong>'.e($user->nama_lengkap).'</strong>,</p>
                <p>Mohon maaf, permintaan reset password Anda ditolak oleh administrator.</p>
                '.($message !== '' ? '<p><strong>Alasan:</strong> '.e($message).'</p>' : '').'
                <p>Jika Anda membutuhkan bantuan, silakan hubungi administrator.</p>
            ',
        ));

        ActivityLog::catat('users', 'reject_reset', 'Penolakan Reset Password', "Menolak reset password untuk {$user->username}");

        return back()->with('success', 'Permintaan reset password berhasil ditolak.');
    }
}
