<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class PasswordResetController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Setara auth/forgot_password_request.php lama:
     * mencatat permintaan reset (reset_requested_at) untuk disetujui
     * Super Admin, TIDAK langsung mengirim link. Respons selalu generik
     * supaya tidak membocorkan apakah username/email terdaftar.
     */
    public function submitRequest(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        $pesanGenerik = 'Permohonan diterima, mohon tunggu persetujuan Super Admin.';

        // Maksimal 3 permohonan per jam per alamat IP
        $throttleKey = 'forgot-password:'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()->with('error', 'Terlalu banyak permohonan. Silakan coba lagi dalam 1 jam.');
        }
        RateLimiter::hit($throttleKey, 3600);

        $user = User::where('username', $data['username'])
            ->where('email', $data['email'])
            ->where('approved', 1)
            ->first();

        if (! $user) {
            return back()->with('success', $pesanGenerik);
        }

        if ($user->reset_requested_at && $user->reset_requested_at->gt(now()->subHours(24))) {
            return back()->with('error', 'Anda sudah mengirim permohonan dalam 24 jam terakhir. Silakan tunggu.');
        }

        $user->forceFill(['reset_requested_at' => now()])->save();

        return back()->with('success', $pesanGenerik);
    }

    public function showResetForm(string $token)
    {
        $user = User::whereNotNull('reset_token')
            ->where('reset_token_expiry', '>', now())
            ->get()
            ->first(fn (User $u) => Hash::check($token, $u->reset_token));

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Link reset password tidak valid atau sudah kedaluwarsa.');
        }

        return view('auth.reset-password', ['token' => $token, 'user' => $user]);
    }

    public function resetPassword(Request $request, string $token)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::whereNotNull('reset_token')
            ->where('reset_token_expiry', '>', now())
            ->get()
            ->first(fn (User $u) => Hash::check($token, $u->reset_token));

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Link reset password tidak valid atau sudah kedaluwarsa.');
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'reset_token' => null,
            'reset_token_expiry' => null,
            'reset_requested_at' => null,
        ])->save();

        return redirect()->route('login')
            ->with('success', 'Password berhasil diubah. Silakan login dengan password baru.');
    }
}
