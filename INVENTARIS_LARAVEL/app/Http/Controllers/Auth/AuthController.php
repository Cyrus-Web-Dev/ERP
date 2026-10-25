<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\GenericNotificationMail;
use App\Models\LoginAttempt;
use App\Models\TwofaCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    // Sama seperti konstanta di login.php lama
    private const MAKS_PERCOBAAN = 5;
    private const DURASI_KUNCI_MENIT = 15;
    private const MAKS_PERCOBAAN_HARI = 20;
    private const DURASI_KUNCI_JAM = 24;

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $ip = $request->ip();
        $username = $credentials['username'];

        if ($this->sedangTerkunci($ip, $username)) {
            return back()
                ->withInput($request->only('username'))
                ->with('error', 'Terlalu banyak percobaan gagal. Akun/IP Anda dikunci sementara. Coba lagi nanti.');
        }

        $user = User::where('username', $username)->first();

        $loginBerhasil = $user && Hash::check($credentials['password'], $user->password);

        $this->catatPercobaan($ip, $username, $loginBerhasil);

        if (! $loginBerhasil) {
            return back()
                ->withInput($request->only('username'))
                ->with('error', 'Username atau password salah.');
        }

        if (! $user->email_verified) {
            return back()->with('error', 'Email Anda belum terverifikasi.');
        }

        if (! $user->approved) {
            return back()->with('error', 'Akun Anda masih menunggu persetujuan Super Admin.');
        }

        if ($user->status !== 'aktif') {
            return back()->with('error', 'Akun Anda sedang tidak aktif. Hubungi administrator.');
        }

        // Login password berhasil -> lanjut ke verifikasi 2FA (bukan langsung login),
        // sama seperti alur login.php + verify_2fa.php di project lama.
        $this->kirimKode2FA($user);

        $request->session()->put('2fa_user_id', $user->id_user);
        $request->session()->put('2fa_pending', true);
        $request->session()->put('2fa_attempts', 0);
        $request->session()->put('2fa_remember', $request->boolean('remember'));

        return redirect()->route('2fa.verify');
    }

    private function kirimKode2FA(User $user): void
    {
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
                <p>Gunakan kode berikut untuk verifikasi 2 faktor:</p>
                <div style="background:#F1F5F9;padding:20px;text-align:center;font-size:36px;font-weight:bold;letter-spacing:10px;border-radius:10px;margin:20px 0;border:2px dashed #14B8A6;">
                    '.$code.'
                </div>
                <p><strong>⏰ Kode ini berlaku selama 5 menit.</strong></p>
                <p>Jika Anda tidak melakukan login, abaikan email ini dan segera ganti password Anda.</p>
            ',
        ));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah keluar.');
    }

    /**
     * Setara sedangTerkunci() di login.php lama:
     * - Terkunci 15 menit kalau >=5 percobaan gagal dalam 15 menit terakhir
     *   (dicek per IP ATAU per username).
     * - Terkunci (lebih lama, dicek lewat window 24 jam) kalau >=20 percobaan
     *   gagal dalam 24 jam terakhir.
     */
    private function sedangTerkunci(string $ip, string $username): bool
    {
        $batas15Menit = now()->subMinutes(self::DURASI_KUNCI_MENIT);

        $jumlah = LoginAttempt::where('berhasil', false)
            ->where(function ($q) use ($ip, $username, $batas15Menit) {
                $q->where(function ($q2) use ($ip, $batas15Menit) {
                    $q2->where('ip_address', $ip)->where('waktu', '>', $batas15Menit);
                })->orWhere(function ($q2) use ($username, $batas15Menit) {
                    $q2->where('username', $username)->where('waktu', '>', $batas15Menit);
                });
            })->count();

        if ($jumlah < self::MAKS_PERCOBAAN) {
            return false;
        }

        $batas24Jam = now()->subHours(self::DURASI_KUNCI_JAM);

        $jumlah24 = LoginAttempt::where('berhasil', false)
            ->where(function ($q) use ($ip, $username, $batas24Jam) {
                $q->where(function ($q2) use ($ip, $batas24Jam) {
                    $q2->where('ip_address', $ip)->where('waktu', '>', $batas24Jam);
                })->orWhere(function ($q2) use ($username, $batas24Jam) {
                    $q2->where('username', $username)->where('waktu', '>', $batas24Jam);
                });
            })->count();

        if ($jumlah24 >= self::MAKS_PERCOBAAN_HARI) {
            return true;
        }

        return $jumlah >= self::MAKS_PERCOBAAN;
    }

    private function catatPercobaan(string $ip, string $username, bool $berhasil): void
    {
        LoginAttempt::create([
            'ip_address' => $ip,
            'username' => $username,
            'waktu' => now(),
            'berhasil' => $berhasil,
            'user_agent' => request()->userAgent(),
        ]);
    }
}
