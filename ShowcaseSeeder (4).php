<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\CompanyHoliday;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplicant;
use App\Models\JobVacancy;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\Position;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\User;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * DATA SHOWCASE (demo) untuk SDM-HRIS.
 *
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * 5 halaman utama:
 *   1. Karyawan    -> 10 karyawan lengkap (identitas, jabatan, atasan, rekening, BPJS, kontak darurat)
 *   2. Absensi     -> ±30 hari kerja terakhir untuk 10 karyawan (hadir, terlambat, WFH, sakit, izin, cuti, alpha)
 *   3. Cuti        -> 10 pengajuan (disetujui, menunggu, ditolak, dibatalkan) - sinkron dengan absensi
 *   4. Payroll     -> 5 periode (3 dibayar, 1 sudah diproses, 1 draf) dihitung PAKAI PayrollCalculator asli (BPJS & PPh 21)
 *   5. Rekrutmen   -> 4 lowongan + 12 pelamar di semua tahap seleksi
 * Bonus: siklus penilaian kinerja + 10 penilaian, 4 pelatihan + peserta, 4 pengumuman, 3 hari libur perusahaan.
 *
 * Karyawan (NIP EMP-101..EMP-110) SAMA dengan data contoh di PAYBRIDGE, jadi bila kedua sistem dipamerkan bersama,
 * nama, jabatan, dan gaji pokoknya konsisten. Aman dijalankan berulang.
 */
class ShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'Demo@12345';

    /** @var array<string,User> */
    private array $users = [];

    /** @var array<string,Employee> nip => Employee */
    private array $emps = [];

    private Carbon $today;

    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, LeaveTypeSeeder::class, DepartmentSeeder::class]);

        $this->today = Carbon::today();
        $this->employees();                 // halaman 1 (+ akun login)
        $this->holidays();
        $leaveDays = $this->leaves();       // halaman 3
        $this->attendances($leaveDays);     // halaman 2
        $this->payroll();                   // halaman 4
        $this->recruitment();               // halaman 5
        $this->performance();
        $this->trainings();
        $this->announcements();

        $this->command?->info('Data showcase SDM-HRIS selesai dibuat.');
        $this->command?->table(['Peran', 'Email', 'Kata sandi'], collect($this->users)->map(fn ($u, $role) => [$role, $u->email, self::PASSWORD])->values()->all());
    }

    /* ------------------------------------------------ 1. KARYAWAN */

    private function employeeRows(): array
    {
        // nip => [nama, L/P, dept, jabatan, ptkp, kawin, tanggungan, gaji, tipe, tahun kerja, atasan, lahir(thn,bln,tgl), tempat lahir, bank, rekening]
        return [
            'EMP-101' => ['Budi Santoso', 'L', 'FIN', 'Staff Keuangan', 'K/1', 'married', 1, 8500000, 'tetap', 4, 'EMP-102', [1990, 5, 17], 'Padang', 'BCA', '8800100101'],
            'EMP-102' => ['Siti Rahmawati', 'P', 'FIN', 'Manajer Keuangan', 'TK/0', 'single', 0, 14000000, 'tetap', 6, 'EMP-110', [1987, 11, 2], 'Bukittinggi', 'Mandiri', '8800100102'],
            'EMP-103' => ['Agus Pratama', 'L', 'OPS', 'Staff Operasional', 'K/2', 'married', 2, 6200000, 'tetap', 3, 'EMP-108', [1992, 3, 24], 'Solok', 'BRI', '8800100103'],
            'EMP-104' => ['Dewi Anggraini', 'P', 'OPS', 'Staff Operasional', 'TK/0', 'single', 0, 6800000, 'tetap', 2, 'EMP-108', [1995, 8, 9], 'Padang Panjang', 'BNI', '8800100104'],
            'EMP-105' => ['Rizky Hidayat', 'L', 'IT', 'Staff IT', 'TK/1', 'divorced', 1, 15500000, 'tetap', 3, 'EMP-107', [1993, 1, 30], 'Pariaman', 'BCA', '8800100105'],
            'EMP-106' => ['Maya Putri Utami', 'P', 'HR', 'Staff HR', 'K/0', 'married', 0, 7500000, 'tetap', 2, 'EMP-110', [1994, 6, 12], 'Padang', 'Mandiri', '8800100106'],
            'EMP-107' => ['Hendra Wijaya', 'L', 'IT', 'Manajer IT', 'K/3', 'married', 3, 32000000, 'tetap', 7, 'EMP-110', [1984, 9, 5], 'Payakumbuh', 'BCA', '8800100107'],
            'EMP-108' => ['Fajar Nugroho', 'L', 'OPS', 'Manajer Operasional', 'K/2', 'married', 2, 38000000, 'tetap', 8, 'EMP-110', [1982, 12, 19], 'Padang', 'BNI', '8800100108'],
            'EMP-109' => ['Rina Kusuma Dewi', 'P', 'MKT', 'Staff Pemasaran', 'TK/0', 'single', 0, 8000000, 'kontrak', 1, 'EMP-110', [1997, 4, 28], 'Sawahlunto', 'BRI', '8800100109'],
            'EMP-110' => ['Dr. Andini Prameswari', 'P', 'DIR', 'Direktur', 'K/2', 'married', 2, 95000000, 'tetap', 9, null, [1980, 2, 14], 'Jakarta', 'Mandiri', '8800100110'],
        ];
    }

    private function employees(): void
    {
        $i = 0;
        foreach ($this->employeeRows() as $nip => [$name, $g, $dept, $title, $ptkp, $marital, $dep, $salary, $type, $yrs, , [$by, $bm, $bd], $pob, $bank, $acct]) {
            $i++;
            $department = Department::where('code', $dept)->first();
            $position = Position::where('department_id', $department?->id)->where('title', $title)->first();
            $first = strtolower(preg_replace('/[^a-z]/i', '', explode(' ', str_replace('Dr. ', '', $name))[0]));
            $join = $this->today->copy()->subYears($yrs)->startOfMonth()->addDays($i);

            $this->emps[$nip] = Employee::updateOrCreate(['nip' => $nip], [
                'full_name' => $name, 'gender' => $g, 'place_of_birth' => $pob, 'date_of_birth' => Carbon::create($by, $bm, $bd)->toDateString(),
                'address' => 'Jl. '.['Ahmad Yani', 'Veteran', 'Diponegoro', 'Hos Cokroaminoto', 'Bundo Kanduang', 'Juanda', 'Sutan Syahrir', 'Perintis Kemerdekaan', 'Adinegoro', 'Khatib Sulaiman'][$i - 1].' No. '.(10 + $i * 3).', Padang, Sumatera Barat',
                'phone' => '0812-3400-'.(1000 + $i), 'personal_email' => $first.'.'.$nip.'@gmail.example', 'marital_status' => $marital, 'dependents_count' => $dep,
                'department_id' => $department?->id, 'position_id' => $position?->id, 'employment_type' => $type, 'join_date' => $join->toDateString(),
                'contract_end_date' => $type === 'kontrak' ? $this->today->copy()->addMonths(8)->endOfMonth()->toDateString() : null, 'employment_status' => 'active',
                'base_salary' => $salary, 'bank_name' => $bank, 'bank_account_number' => $acct, 'bank_account_name' => $name,
                'npwp' => sprintf('09.254.%03d.%d-407.000', 290 + $i, $i % 9), 'bpjs_kesehatan_number' => '000'.(1234567000 + $i), 'bpjs_tk_number' => '25'.(1000000000 + $i * 7),
                'ptkp_status' => $ptkp, 'emergency_contact_name' => ['Ratna Sari', 'Hasan Basri', 'Yulianti', 'Darmawan', 'Fitri Amelia'][$i % 5], 'emergency_contact_phone' => '0813-5500-'.(2000 + $i),
                'notes' => null,
            ]);
        }

        // atasan langsung & kepala departemen
        foreach ($this->employeeRows() as $nip => $row) {
            if ($row[10]) {
                $this->emps[$nip]->update(['supervisor_id' => $this->emps[$row[10]]->id]);
            }
        }
        foreach (['FIN' => 'EMP-102', 'OPS' => 'EMP-108', 'IT' => 'EMP-107', 'HR' => 'EMP-106', 'DIR' => 'EMP-110'] as $code => $nip) {
            Department::where('code', $code)->update(['manager_employee_id' => $this->emps[$nip]->id]);
        }

        // akun login demo yang terhubung ke data karyawan (tiap peran punya tampilan berbeda)
        foreach ([['hr-admin', 'EMP-106', 'maya.hr@contoh.test'], ['manajer', 'EMP-108', 'fajar.manajer@contoh.test'], ['karyawan', 'EMP-103', 'agus.karyawan@contoh.test']] as [$role, $nip, $email]) {
            $emp = $this->emps[$nip];
            $u = User::where('email', $email)->first()
                ?? User::create(['name' => $emp->full_name, 'email' => $email, 'password' => self::PASSWORD, 'status' => 'active', 'must_change_password' => false]);
            if (! $u->email_verified_at) {
                $u->forceFill(['email_verified_at' => now()])->save();
            }
            $u->syncRoles([$role]);
            if ((int) $emp->user_id !== $u->id) {
                $emp->update(['user_id' => $u->id]);
            }
            $this->users[$role] = $u;
        }
    }

    /* ----------------------------------------------------- 3. CUTI */

    /**
     * @return array<string,array<string,string>> nip => [tanggal => 'cuti'|'sakit'|'izin'] (dipakai agar absensi sinkron)
     */
    private function leaves(): array
    {
        $type = fn (string $n) => LeaveType::where('name', $n)->first();
        $t = $this->today;
        $hr = $this->users['hr-admin'];
        $mgr = $this->users['manajer'];

        // nip, jenis, mulai (offset hari dari hari ini), durasi hari kerja, alasan, status, penyetuju, catatan penolakan
        $rows = [
            ['EMP-101', 'Cuti Tahunan', -40, 3, 'Liburan keluarga ke Bukittinggi.', 'approved', $mgr, null],
            ['EMP-104', 'Cuti Sakit', -19, 2, 'Demam tinggi, sudah berobat ke dokter.', 'approved', $hr, null],
            ['EMP-102', 'Cuti Tahunan', -33, 5, 'Menghadiri pernikahan adik di Jakarta.', 'approved', $hr, null],
            ['EMP-105', 'Izin Pribadi', -12, 1, 'Mengurus perpanjangan SIM dan STNK.', 'approved', $mgr, null],
            ['EMP-109', 'Cuti Tahunan', -8, 2, 'Keperluan keluarga di Sawahlunto.', 'approved', $hr, null],
            ['EMP-106', 'Cuti Duka (Keluarga Inti)', -5, 2, 'Kakek meninggal dunia.', 'approved', $hr, null],
            ['EMP-103', 'Cuti Tahunan', 4, 3, 'Acara keluarga besar di Solok.', 'pending', null, null],
            ['EMP-107', 'Cuti Tahunan', 14, 5, 'Liburan akhir tahun bersama keluarga.', 'pending', null, null],
            ['EMP-108', 'Cuti Tahunan', 9, 4, 'Mendampingi anak ujian masuk sekolah.', 'rejected', $hr, 'Bertepatan dengan audit tahunan operasional. Mohon ajukan ulang setelah audit selesai.'],
            ['EMP-104', 'Cuti Tanpa Gaji', 20, 3, 'Mengurus keperluan pribadi di luar kota.', 'cancelled', null, null],
        ];

        $map = [];
        foreach ($rows as [$nip, $typeName, $offset, $days, $reason, $status, $by, $rejectNote]) {
            $emp = $this->emps[$nip];
            $lt = $type($typeName);
            $start = $this->nextWorkday($t->copy()->addDays($offset));
            $end = $this->addWorkdays($start, $days - 1);

            Leave::firstOrCreate(['employee_id' => $emp->id, 'leave_type_id' => $lt->id, 'start_date' => $start->toDateString()], [
                'end_date' => $end->toDateString(), 'total_days' => $days, 'reason' => $reason, 'status' => $status,
                'approved_by' => in_array($status, ['approved', 'rejected'], true) ? $by->id : null,
                'approved_at' => in_array($status, ['approved', 'rejected'], true) ? $start->copy()->subDays(3) : null,
                'rejection_reason' => $rejectNote,
            ]);

            if ($status === 'approved' && $end->lt($t)) {
                $label = str_contains($typeName, 'Sakit') ? 'sakit' : (str_contains($typeName, 'Izin') ? 'izin' : 'cuti');
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    if ($d->isWeekday()) {
                        $map[$nip][$d->toDateString()] = $label;
                    }
                }
            }
        }

        return $map;
    }

    private function nextWorkday(Carbon $d): Carbon
    {
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }

    private function addWorkdays(Carbon $start, int $n): Carbon
    {
        $d = $start->copy();
        while ($n > 0) {
            $d->addDay();
            if ($d->isWeekday()) {
                $n--;
            }
        }

        return $d;
    }

    /* -------------------------------------------------- 2. ABSENSI */

    private function attendances(array $leaveDays): void
    {
        $t = $this->today;
        $holidays = CompanyHoliday::pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $alpha = ['EMP-109' => 9, 'EMP-103' => 17]; // beberapa hari alpha supaya rekap terlihat nyata (hari ke-N ke belakang)

        for ($back = 31; $back >= 1; $back--) {
            $d = $t->copy()->subDays($back);
            if ($d->isWeekend() || in_array($d->toDateString(), $holidays, true)) {
                continue;
            }
            foreach ($this->emps as $nip => $emp) {
                $key = $nip.$d->toDateString();
                $h = crc32($key);
                $override = $leaveDays[$nip][$d->toDateString()] ?? null;

                if ($override) {
                    Attendance::updateOrCreate(['employee_id' => $emp->id, 'date' => $d->toDateString()], [
                        'check_in' => null, 'check_out' => null, 'status' => $override, 'late_minutes' => 0, 'notes' => ['cuti' => 'Cuti disetujui', 'sakit' => 'Sakit (disetujui)', 'izin' => 'Izin disetujui'][$override],
                    ]);

                    continue;
                }
                if (isset($alpha[$nip]) && $alpha[$nip] === $back) {
                    Attendance::updateOrCreate(['employee_id' => $emp->id, 'date' => $d->toDateString()], ['check_in' => null, 'check_out' => null, 'status' => 'alpha', 'late_minutes' => 0, 'notes' => 'Tidak ada keterangan']);

                    continue;
                }

                $wfh = $h % 11 === 0;
                $offset = ($h % 41) - 22;                    // -22..+18 menit dari 08:00
                $late = $offset > 15 ? $offset : 0;          // toleransi 15 menit (sesuai config hr.late_tolerance_minutes)
                $in = Carbon::create(2000, 1, 1, 8, 0, 0)->addMinutes($offset);
                $out = Carbon::create(2000, 1, 1, 17, 0, 0)->addMinutes(($h >> 3) % 55);

                $lat = -0.9492 + (($h % 90) - 45) / 100000;
                $lng = 100.3543 + (($h % 70) - 35) / 100000;
                Attendance::updateOrCreate(['employee_id' => $emp->id, 'date' => $d->toDateString()], [
                    'check_in' => $in->format('H:i:s'), 'check_out' => $out->format('H:i:s'),
                    'check_in_lat' => $wfh ? null : $lat, 'check_in_lng' => $wfh ? null : $lng, 'check_out_lat' => $wfh ? null : $lat, 'check_out_lng' => $wfh ? null : $lng,
                    'status' => $wfh ? 'wfh' : ($late > 0 ? 'terlambat' : 'hadir'), 'late_minutes' => $wfh ? 0 : $late,
                    'notes' => $wfh ? 'Bekerja dari rumah' : ($late > 0 ? 'Terlambat karena kemacetan' : null),
                ]);
            }
        }

        // hari ini (bila hari kerja): sebagian sudah check-in, belum check-out
        if ($t->isWeekday() && ! in_array($t->toDateString(), $holidays, true)) {
            foreach (array_slice(array_keys($this->emps), 0, 8) as $k => $nip) {
                Attendance::updateOrCreate(['employee_id' => $this->emps[$nip]->id, 'date' => $t->toDateString()], [
                    'check_in' => sprintf('07:%02d:00', 40 + $k * 2), 'check_out' => null, 'check_in_lat' => -0.9492, 'check_in_lng' => 100.3543, 'status' => 'hadir', 'late_minutes' => 0,
                ]);
            }
        }
    }

    /* ------------------------------------------------- 4. PAYROLL */

    private function payroll(): void
    {
        $calc = app(PayrollCalculator::class);
        $hr = $this->users['hr-admin'];

        $allow = function (string $nip): array {
            $a = [['label' => 'Tunjangan Transport', 'amount' => 500000], ['label' => 'Tunjangan Makan', 'amount' => 600000]];
            $jabatan = ['EMP-102' => 2500000, 'EMP-107' => 5000000, 'EMP-108' => 6000000, 'EMP-110' => 25000000, 'EMP-105' => 2000000, 'EMP-101' => 1500000];
            if (isset($jabatan[$nip])) {
                array_unshift($a, ['label' => 'Tunjangan Jabatan', 'amount' => $jabatan[$nip]]);
            }

            return $a;
        };

        // [bulan lalu ke-N, status periode]
        foreach ([[4, 'paid'], [3, 'paid'], [2, 'paid'], [1, 'processed'], [0, 'draft']] as [$ago, $status]) {
            $m = $this->today->copy()->startOfMonth()->subMonths($ago);
            $label = $m->locale('id')->translatedFormat('F Y');
            $payDate = $m->copy()->endOfMonth()->subDays(3)->setTime(15, 0);
            $period = PayrollPeriod::updateOrCreate(['period_month' => $m->month, 'period_year' => $m->year], [
                'status' => $status, 'processed_by' => $status === 'draft' ? null : $hr->id, 'processed_at' => $status === 'draft' ? null : $m->copy()->endOfMonth()->subDays(6)->setTime(10, 0),
                'paid_at' => $status === 'paid' ? $payDate : null, 'notes' => $status === 'draft' ? 'Periode berjalan: siap diproses di akhir bulan.' : 'Gaji '.$label.'.',
            ]);
            if ($status === 'draft') {
                continue;
            }

            foreach ($this->emps as $nip => $emp) {
                $overtime = in_array($nip, ['EMP-103', 'EMP-104'], true) ? 450000 + (($m->month + (int) substr($nip, -1)) % 4) * 150000 : 0;
                $deduct = in_array($nip, ['EMP-101', 'EMP-102', 'EMP-106'], true) ? [['label' => 'Iuran Koperasi', 'amount' => 50000]] : [];
                if ($nip === 'EMP-103' && $ago <= 3) {
                    $deduct[] = ['label' => 'Cicilan Kasbon', 'amount' => 1000000];
                }
                $row = $calc->calculate($emp, $allow($nip), $deduct, (float) $overtime);
                PayrollItem::updateOrCreate(['payroll_period_id' => $period->id, 'employee_id' => $emp->id], $row + [
                    'status' => $status === 'paid' ? 'paid' : 'draft', 'paid_at' => $status === 'paid' ? $payDate : null, 'payment_method' => $status === 'paid' ? 'transfer' : null,
                    'notes' => $overtime ? 'Termasuk lembur' : null,
                ]);
            }
        }
    }

    /* ----------------------------------------------- 5. REKRUTMEN */

    private function recruitment(): void
    {
        $hr = $this->users['hr-admin'];
        $pos = fn (string $title) => Position::where('title', $title)->first();
        $t = $this->today;

        $vacancies = [
            ['Software Engineer (Laravel & Vue)', 'IT', 'Staff IT', 'tetap', 2, 'open', -25, 20, 'Mengembangkan dan memelihara modul ERP perusahaan (Laravel, Vue, PostgreSQL), menulis pengujian otomatis, dan berkolaborasi dengan tim Produk.', "S1 Teknik Informatika/Sistem Informasi\nMinimal 2 tahun pengalaman Laravel\nMenguasai Vue 3 dan PostgreSQL\nMemahami REST API dan Git"],
            ['Staff Akuntansi & Pajak', 'FIN', 'Staff Keuangan', 'kontrak', 1, 'open', -18, 27, 'Menyusun jurnal, rekonsiliasi bank, dan pelaporan pajak bulanan (PPN dan PPh).', "S1 Akuntansi\nMemahami PSAK dan perpajakan dasar\nTeliti dan mahir Excel\nFresh graduate dipersilakan"],
            ['Digital Marketing Specialist', 'MKT', 'Staff Pemasaran', 'kontrak', 1, 'open', -10, 35, 'Mengelola kampanye iklan digital, konten media sosial, dan analitik performa penjualan.', "Minimal D3/S1 semua jurusan\nPengalaman Meta/Google Ads\nKemampuan analisis data dasar\nKreatif dan komunikatif"],
            ['Staff Gudang & Logistik', 'OPS', 'Staff Operasional', 'tetap', 3, 'closed', -70, -15, 'Mengelola penerimaan, penyimpanan, dan pengiriman barang serta stok opname rutin.', "Minimal SMA/SMK\nPengalaman gudang diutamakan\nFisik sehat dan bersedia lembur\nMemiliki SIM C"],
        ];

        $v = [];
        foreach ($vacancies as $k => [$title, $dept, $posTitle, $type, $slots, $status, $openOff, $closeOff, $desc, $req]) {
            $department = Department::where('code', $dept)->first();
            $slug = Str::slug($title).'-demo';
            $v[$k] = JobVacancy::updateOrCreate(['slug' => $slug], [
                'title' => $title, 'department_id' => $department?->id, 'position_id' => $pos($posTitle)?->id, 'employment_type' => $type, 'description' => $desc,
                'requirements' => $req, 'slots' => $slots, 'status' => $status, 'posted_by' => $hr->id,
                'opens_at' => $t->copy()->addDays($openOff)->toDateString(), 'closes_at' => $t->copy()->addDays($closeOff)->toDateString(),
            ]);
        }

        // vacancy index, nama, email, telepon, tahap, skor, sumber, hari lalu, catatan
        $applicants = [
            [0, 'Andika Pratama Putra', 'andika.putra@mail.example', '0812-9000-1101', 'interview', 86, 'linkedin', 14, 'Portofolio kuat: pernah membangun sistem inventaris Laravel. Wawancara teknis dijadwalkan.'],
            [0, 'Nabila Salsabila', 'nabila.s@mail.example', '0813-9000-1102', 'offer', 92, 'referral', 21, 'Lulus tes teknis dengan nilai tertinggi. Menunggu persetujuan penawaran gaji.'],
            [0, 'Reza Firmansyah', 'reza.firman@mail.example', '0821-9000-1103', 'screening', 70, 'jobstreet', 6, 'CV sesuai, menunggu tes teknis.'],
            [0, 'Gita Maharani', 'gita.maharani@mail.example', '0852-9000-1104', 'rejected', 45, 'jobstreet', 17, 'Pengalaman belum sesuai kualifikasi minimum.'],
            [1, 'Yusuf Hakim', 'yusuf.hakim@mail.example', '0811-9000-1201', 'interview', 81, 'website', 9, 'Pengalaman 3 tahun di KAP, memahami PPh 21/23 dan PPN.'],
            [1, 'Tiara Anjani', 'tiara.anjani@mail.example', '0822-9000-1202', 'applied', null, 'website', 2, null],
            [1, 'Dimas Aryo Seno', 'dimas.seno@mail.example', '0813-9000-1203', 'screening', 66, 'linkedin', 7, 'Fresh graduate, IPK 3,6. Perlu tes Excel.'],
            [2, 'Sasha Kirana', 'sasha.kirana@mail.example', '0877-9000-1301', 'applied', null, 'instagram', 1, null],
            [2, 'Bagas Wicaksono', 'bagas.w@mail.example', '0812-9000-1302', 'screening', 74, 'referral', 5, 'Pernah mengelola anggaran iklan Rp50 juta/bulan di UMKM.'],
            [3, 'Hafizh Alfarizi', 'hafizh.alf@mail.example', '0856-9000-1401', 'hired', 88, 'walk-in', 45, 'Diterima, mulai bekerja bulan lalu.'],
            [3, 'Putri Ayu Lestari', 'putri.ayu@mail.example', '0813-9000-1402', 'hired', 84, 'jobstreet', 50, 'Diterima sebagai staf gudang shift pagi.'],
            [3, 'Ilham Maulana', 'ilham.maulana@mail.example', '0821-9000-1403', 'rejected', 58, 'walk-in', 52, 'Tidak lulus tes fisik dan tidak memiliki SIM C.'],
        ];
        foreach ($applicants as [$vi, $name, $email, $phone, $stage, $score, $source, $ago, $notes]) {
            JobApplicant::firstOrCreate(['job_vacancy_id' => $v[$vi]->id, 'email' => $email], [
                'full_name' => $name, 'phone' => $phone, 'cover_letter' => 'Saya tertarik bergabung sebagai '.$v[$vi]->title.' di perusahaan Bapak/Ibu dan siap mengikuti seluruh tahapan seleksi.',
                'stage' => $stage, 'score' => $score, 'source' => $source, 'notes' => $notes, 'applied_at' => $t->copy()->subDays($ago)->setTime(9 + $ago % 8, 20),
            ]);
        }
    }

    /* ------------------------------------------------- bonus: KINERJA */

    private function performance(): void
    {
        $t = $this->today;
        $y = $t->year;
        $mgr = $this->users['manajer'];

        $closed = PerformanceReviewCycle::firstOrCreate(['name' => "Semester 1 {$y}"], ['start_date' => "{$y}-01-01", 'end_date' => "{$y}-06-30", 'status' => 'closed', 'created_by' => $this->users['hr-admin']->id]);
        PerformanceReviewCycle::firstOrCreate(['name' => "Semester 2 {$y}"], ['start_date' => "{$y}-07-01", 'end_date' => "{$y}-12-31", 'status' => 'open', 'created_by' => $this->users['hr-admin']->id]);

        $kpiSets = [
            'EMP-101' => [['Ketepatan penutupan buku bulanan', 100, 96, 40], ['Akurasi rekonsiliasi bank', 100, 99, 30], ['Kepatuhan jadwal pelaporan', 100, 92, 30]],
            'EMP-102' => [['Efisiensi biaya operasional', 100, 94, 40], ['Ketepatan laporan manajemen', 100, 97, 30], ['Pembinaan tim keuangan', 100, 88, 30]],
            'EMP-103' => [['Akurasi stok opname', 100, 91, 40], ['Ketepatan bongkar muat', 100, 86, 35], ['Kepatuhan K3', 100, 95, 25]],
            'EMP-104' => [['Ketepatan pengiriman', 100, 90, 45], ['Kepuasan pelanggan', 100, 87, 35], ['Administrasi logistik', 100, 93, 20]],
            'EMP-105' => [['Penyelesaian tiket TI', 100, 95, 40], ['Uptime layanan', 100, 99, 35], ['Dokumentasi teknis', 100, 80, 25]],
            'EMP-106' => [['Penyelesaian administrasi karyawan', 100, 94, 40], ['Kecepatan proses rekrutmen', 100, 85, 35], ['Kepuasan karyawan', 100, 90, 25]],
            'EMP-107' => [['Delivery proyek ERP', 100, 92, 50], ['Kualitas arsitektur sistem', 100, 90, 30], ['Pengembangan tim', 100, 85, 20]],
            'EMP-108' => [['Pencapaian target operasional', 100, 96, 50], ['Efisiensi biaya logistik', 100, 89, 30], ['Keselamatan kerja', 100, 98, 20]],
            'EMP-109' => [['Pertumbuhan leads digital', 100, 78, 50], ['ROAS kampanye', 100, 72, 30], ['Konsistensi konten', 100, 84, 20]],
            'EMP-110' => [['Pertumbuhan pendapatan', 100, 93, 50], ['Laba operasional', 100, 91, 30], ['Tata kelola perusahaan', 100, 95, 20]],
        ];
        $texts = [
            'strengths' => ['Teliti, disiplin tenggat, dan komunikatif dengan tim lintas divisi.', 'Inisiatif tinggi dan mampu bekerja mandiri.', 'Kolaboratif dan konsisten menjaga kualitas pekerjaan.'],
            'improvements' => ['Perlu meningkatkan dokumentasi proses kerja.', 'Perlu lebih proaktif menyampaikan kendala lebih awal.', 'Tingkatkan keterampilan analisis data untuk pengambilan keputusan.'],
        ];

        $i = 0;
        foreach ($kpiSets as $nip => $set) {
            $i++;
            $items = array_map(fn ($k) => ['indicator' => $k[0], 'target' => $k[1], 'achieved' => $k[2], 'weight' => $k[3]], $set);
            $score = round(collect($items)->sum(fn ($k) => $k['achieved'] / max($k['target'], 1) * $k['weight']), 2);
            $rating = $score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 70 ? 'C' : ($score >= 60 ? 'D' : 'E')));
            PerformanceReview::updateOrCreate(['cycle_id' => $closed->id, 'employee_id' => $this->emps[$nip]->id], [
                'reviewer_id' => $mgr->id, 'kpi_items' => $items, 'final_score' => $score, 'rating' => $rating,
                'strengths' => $texts['strengths'][$i % 3], 'improvements' => $texts['improvements'][$i % 3],
                'employee_comment' => $i % 2 ? 'Terima kasih atas penilaiannya, saya akan fokus pada area perbaikan.' : null,
                'status' => $i <= 8 ? 'acknowledged' : 'submitted', 'submitted_at' => Carbon::create($y, 7, 5 + $i)->setTime(10, 0), 'acknowledged_at' => $i <= 8 ? Carbon::create($y, 7, 9 + $i)->setTime(14, 30) : null,
            ]);
        }
    }

    /* ------------------------------------------------ bonus: PELATIHAN */

    private function trainings(): void
    {
        $t = $this->today;
        $hr = $this->users['hr-admin'];
        $rows = [
            ['Dasar-dasar K3 di Tempat Kerja', 'Pengenalan keselamatan dan kesehatan kerja untuk seluruh karyawan operasional.', 'Ir. Budiman, M.K3', 'Balai K3 Sumatera Barat', -60, -59, 'Aula Kantor Pusat', 30, 'completed', ['EMP-103' => ['completed', 92], 'EMP-104' => ['completed', 88], 'EMP-108' => ['completed', 95], 'EMP-109' => ['attended', null]]],
            ['Pelatihan Perpajakan PPh 21 & Coretax', 'Update aturan PPh 21 TER dan pelaporan lewat Coretax.', 'Drs. Rahman, Ak., BKP', 'Kantor Jasa Akuntan Rahman & Rekan', -25, -24, 'Ruang Rapat Lantai 2', 15, 'completed', ['EMP-101' => ['completed', 90], 'EMP-102' => ['completed', 94], 'EMP-106' => ['completed', 85]]],
            ['Workshop Keamanan Aplikasi Web (OWASP)', 'Praktik mengamankan aplikasi Laravel dari kerentanan umum.', 'Rina Wulandari', 'Komunitas Cyber Sumbar', 7, 8, 'Lab Komputer TI', 12, 'scheduled', ['EMP-105' => ['registered', null], 'EMP-107' => ['registered', null], 'EMP-109' => ['registered', null]]],
            ['Kepemimpinan untuk Manajer Baru', 'Komunikasi efektif, delegasi, dan pemberian umpan balik.', 'Dr. Fahmi Alamsyah', 'Lembaga Pengembangan SDM Nusantara', 21, 23, 'Hotel Santika Padang', 10, 'scheduled', ['EMP-102' => ['registered', null], 'EMP-108' => ['registered', null], 'EMP-107' => ['registered', null]]],
        ];
        foreach ($rows as [$title, $desc, $trainer, $provider, $s, $e, $loc, $quota, $status, $parts]) {
            $tr = Training::firstOrCreate(['title' => $title], ['description' => $desc, 'trainer' => $trainer, 'provider' => $provider, 'start_date' => $t->copy()->addDays($s)->toDateString(),
                'end_date' => $t->copy()->addDays($e)->toDateString(), 'location' => $loc, 'quota' => $quota, 'status' => $status, 'created_by' => $hr->id]);
            foreach ($parts as $nip => [$st, $score]) {
                TrainingParticipant::firstOrCreate(['training_id' => $tr->id, 'employee_id' => $this->emps[$nip]->id], ['status' => $st, 'score' => $score]);
            }
        }
    }

    /* --------------------------------------------- bonus: PENGUMUMAN */

    private function announcements(): void
    {
        $hr = $this->users['hr-admin'];
        $t = $this->today;
        $rows = [
            ['Jadwal Libur & Cuti Bersama Akhir Tahun', 'Seluruh karyawan diharapkan mengajukan cuti akhir tahun paling lambat dua minggu sebelumnya melalui menu Cuti agar operasional tetap berjalan.', true, -3],
            ['Pembaruan Kebijakan Absensi Berbasis Lokasi', 'Mulai bulan ini absensi dari kantor wajib dilakukan di radius kantor. Karyawan WFH cukup memilih status WFH dan melampirkan catatan kegiatan.', true, -8],
            ['Selamat Datang Karyawan Baru Divisi Operasional', 'Kami menyambut Hafizh Alfarizi dan Putri Ayu Lestari yang bergabung di tim gudang & logistik. Mohon bantu proses adaptasi mereka.', false, -20],
            ['Pengingat: Pengisian Penilaian Kinerja Semester 2', 'Siklus penilaian kinerja Semester 2 sudah dibuka. Atasan langsung diminta menyelesaikan penilaian sebelum akhir bulan depan.', false, -1],
        ];
        foreach ($rows as [$title, $body, $pinned, $ago]) {
            Announcement::firstOrCreate(['title' => $title], ['body' => $body, 'published_by' => $hr->id, 'is_pinned' => $pinned, 'published_at' => $t->copy()->addDays($ago)->setTime(8, 30)]);
        }
    }

    /* -------------------------------------------------- bonus: LIBUR */

    private function holidays(): void
    {
        $y = $this->today->year;
        foreach ([["{$y}-08-18", 'Cuti Bersama Internal HUT Kemerdekaan'], ["{$y}-10-30", 'Hari Ulang Tahun Perusahaan'], ["{$y}-12-31", 'Libur Akhir Tahun Perusahaan']] as [$date, $name]) {
            CompanyHoliday::firstOrCreate(['date' => $date], ['name' => $name, 'type' => 'holiday', 'created_by' => $this->users['hr-admin']->id]);
        }
    }
}
