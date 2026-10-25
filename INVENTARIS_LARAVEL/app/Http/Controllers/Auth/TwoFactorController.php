<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\GenericNotificationMail;
use App\Models\TwofaCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorController extends Controller
{
    private const MAKS_PERCOBAAN = 8;

    public function show(Request $request)
    {
        if (! $request->session()->get('2fa_pending')) {
            return redirect()->route('login');
        }

        return view('auth.verify-2fa');
    }

    public function verify(Request $request)
    {
        if (! $request->session()->get('2fa_pending') || ! $request->session()->get('2fa_user_id')) {
            return redirect()->route('login');
        }

        $attempts = (int) $request->session()->get('2fa_attempts', 0);

        if ($attempts >= self::MAKS_PERCOBAAN) {
            $this->batalkan2FA($request);

            return redirect()->route('login')->with('error', 'Terlalu banyak percobaan salah. Silakan login ulang.');
        }

        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $userId = $request->session()->get('2fa_user_id');

        $kodeAktif = TwofaCode::where('user_id', $userId)
            ->where('expires_at', '>=', now())
            ->latest('id')
            ->first();

        if (! $kodeAktif || ! Hash::check($data['code'], $kodeAktif->code_hash)) {
            $percobaanBaru = ((int) $request->session()->get('2fa_attempts', 0)) + 1;
            $request->session()->put('2fa_attempts', $percobaanBaru);

            $sisa = self::MAKS_PERCOBAAN - $percobaanBaru;

            return back()->with('error', "Kode salah atau sudah kedaluwarsa. Sisa percobaan: {$sisa}.");
        }

        $kodeAktif->delete();

        $user = User::findOrFail($userId);
        $remember = (bool) $request->session()->get('2fa_remember', false);

        $this->batalkan2FA($request);

        $user->forceFill(['last_login_at' => now()])->save();

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Selamat datang kembali, '.$user->nama_lengkap.'!');
    }

    public function resend(Request $request)
    {
        if (! $request->session()->get('2fa_pending') || ! $request->session()->get('2fa_user_id')) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa-resend:'.$request->session()->get('2fa_user_id');
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $detik = RateLimiter::availableIn($throttleKey);

            return back()->with('error', "Tunggu {$detik} detik lagi sebelum kirim ulang kode.");
        }
        RateLimiter::hit($throttleKey, 30);

        $user = User::findOrFail($request->session()->get('2fa_user_id'));
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        TwofaCode::where('user_id', $user->id_user)->delete();
        TwofaCode::create([
            'user_id' => $user->id_user,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
        ]);

        Mail::to($user->email)->send(new GenericNotificationMail(
            subjectLine: '🔐 Kode Verifikasi 2FA - Inventaris Aset',
            heading: 'Verifikasi 2 Faktor',
            headingColor: '#0F172A',
            bodyHtml: '
                <p>Halo <strong>'.e($user->nama_lengkap).'</strong>,</p>
                <p>Kode verifikasi baru Anda:</p>
                <div style="background:#F1F5F9;padding:20px;text-align:center;font-size:36px;font-weight:bold;letter-spacing:10px;border-radius:10px;margin:20px 0;border:2px dashed #14B8A6;">
                    '.$code.'
                </div>
                <p><strong>⏰ Kode ini berlaku selama 5 menit.</strong></p>
            ',
        ));

        return back()->with('success', 'Kode baru telah dikirim ke email Anda.');
    }

    private function batalkan2FA(Request $request): void
    {
        $request->session()->forget(['2fa_user_id', '2fa_pending', '2fa_attempts', '2fa_remember']);
    }
}
