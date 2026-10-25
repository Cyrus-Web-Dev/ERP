<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\GenericNotificationMail;
use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    // Kode berlaku 5 menit (project lama 60 detik, diperpanjang supaya
    // realistis untuk pengiriman email sungguhan yang kadang butuh waktu).
    private const KODE_BERLAKU_MENIT = 5;

    private const MAKS_PERCOBAAN_KODE = 5;

    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * AJAX: kirim kode verifikasi 6 digit ke email.
     * Setara send_verification.php lama.
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->string('email')->trim()->toString();

        // Cooldown 30 detik antar pengiriman untuk email yang sama
        $throttleKey = 'send-code:'.strtolower($email);
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $detik = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'success' => false,
                'message' => "Tunggu {$detik} detik lagi sebelum mengirim ulang kode.",
            ]);
        }

        // Kalau email sudah terdaftar & sudah disetujui, tolak
        $existing = User::where('email', $email)->first();
        if ($existing && $existing->approved) {
            return response()->json(['success' => false, 'message' => 'Email sudah terdaftar dan disetujui.']);
        }
        if ($existing && $existing->email_verified) {
            return response()->json(['success' => false, 'message' => 'Email sudah terverifikasi.']);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerification::where('email', $email)->delete();
        EmailVerification::create([
            'email' => $email,
            'code' => $code,
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::KODE_BERLAKU_MENIT),
        ]);

        RateLimiter::hit($throttleKey, 30);

        Mail::to($email)->send(new GenericNotificationMail(
            subjectLine: 'Kode Verifikasi Registrasi',
            heading: 'Verifikasi Email',
            headingColor: '#0F172A',
            bodyHtml: '
                <p>Gunakan kode berikut untuk verifikasi email Anda:</p>
                <div style="background:#F1F5F9;padding:20px;text-align:center;font-size:32px;font-weight:bold;letter-spacing:10px;border-radius:8px;margin:20px 0;border:2px dashed #0F172A;">
                    '.$code.'
                </div>
                <p>Kode ini berlaku selama '.self::KODE_BERLAKU_MENIT.' menit.</p>
            ',
        ));

        return response()->json(['success' => true, 'message' => 'Kode verifikasi telah dikirim ke email Anda.']);
    }

    /**
     * AJAX: verifikasi kode 6 digit.
     * Setara verify_code.php lama.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = $request->string('email')->trim()->toString();

        $verification = EmailVerification::where('email', $email)->latest('id')->first();

        if (! $verification) {
            return response()->json(['success' => false, 'message' => 'Belum ada kode dikirim ke email ini.']);
        }

        if ($verification->isExpired()) {
            return response()->json(['success' => false, 'message' => 'Kode sudah kedaluwarsa, kirim ulang kode.']);
        }

        if ($verification->attempts >= self::MAKS_PERCOBAAN_KODE) {
            return response()->json(['success' => false, 'message' => 'Terlalu banyak percobaan salah. Kirim ulang kode.']);
        }

        if (! hash_equals($verification->code, $request->string('code')->toString())) {
            $verification->increment('attempts');

            return response()->json(['success' => false, 'message' => 'Kode verifikasi salah.']);
        }

        // Tandai di session bahwa email ini sudah terverifikasi,
        // supaya form registrasi utama (store()) bisa memastikannya.
        $request->session()->put('register_verified_email', $email);
        $verification->delete();

        return response()->json(['success' => true, 'message' => 'Email berhasil diverifikasi.']);
    }

    /**
     * Submit form registrasi akhir. Setara process_register.php lama.
     */
    public function store(Request $request)
    {
        $verifiedEmail = $request->session()->get('register_verified_email');

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:pengelola,pembaca'],
            'jabatan' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
        ]);

        if (! $verifiedEmail || strcasecmp($verifiedEmail, $data['email']) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'Silakan verifikasi email terlebih dahulu.',
            ]);
        }

        if ($data['role'] === 'pengelola') {
            $levelAkses = 'super_admin';
            $idRole = 1;
        } else {
            $levelAkses = 'admin';
            $idRole = 2;
        }

        $isFirstUser = User::isFirstUser();
        $autoApproved = $isFirstUser && $data['role'] === 'pengelola';

        $user = User::create([
            'nama_lengkap' => $data['nama_lengkap'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'jabatan' => $data['jabatan'],
            'email' => $data['email'],
            'status' => 'aktif',
            'id_role' => $idRole,
            'level_akses' => $levelAkses,
            'approved' => $autoApproved,
            'email_verified' => true,
            'registration_date' => now(),
        ]);

        if ($autoApproved) {
            $user->forceFill(['approved_at' => now()])->save();
        }

        $request->session()->forget('register_verified_email');

        if ($autoApproved) {
            return redirect()->route('login')
                ->with('success', 'Pendaftaran berhasil! Anda adalah Super Admin pertama. Silakan login.');
        }

        return redirect()->route('login')
            ->with('success', 'Pendaftaran berhasil! Tunggu persetujuan dari Super Admin sebelum bisa login.');
    }
}
