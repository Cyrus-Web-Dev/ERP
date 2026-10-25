<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\AttendanceSnapshot;
use App\Models\AuditEntry;
use App\Models\BudgetLine;
use App\Models\BudgetPlan;
use App\Models\CompensationContract;
use App\Models\Department;
use App\Models\DisbursementBatch;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\FundingRequest;
use App\Models\Grade;
use App\Models\IntegrationMessage;
use App\Models\LoanRepayment;
use App\Models\PayComponent;
use App\Models\PayrollFinding;
use App\Models\PayrollInput;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Remittance;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLedger;
use App\Services\Calc\BpjsCalculator;
use App\Services\Calc\PphCalculator;
use App\Services\PayrollIntegrity;
use App\Services\Regulations;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DATA SHOWCASE (demo) untuk PAYBRIDGE.
 *
 *   php artisan db:seed --class=ShowcaseSeeder
 *
 * Mengisi 5 halaman utama dengan data yang saling nyambung (angka dihitung memakai kalkulator BPJS & PPh 21 TER asli aplikasi):
 *   1. Karyawan            -> 10 karyawan (NIP EMP-101..EMP-110) lengkap dengan absensi 8 bulan
 *   2. Kontrak kompensasi  -> 10 kontrak aktif + riwayat versi, usulan menunggu persetujuan, dan satu yang ditolak
 *   3. Putaran payroll     -> 7 gaji bulanan (terkunci), 1 THR, 1 putaran bulan ini menunggu persetujuan
 *                             (otomatis ikut: pengajuan dana, setoran pajak/BPJS, batch transfer bank, temuan, jejak audit)
 *   4. Input variabel & Kasbon -> lembur, bonus, tunjangan, koreksi + 8 kasbon
 *   5. Anggaran SDM        -> 4 rencana anggaran (aktif, superseded, ditolak, draf) per departemen x bulan
 *
 * Aman dijalankan berulang (data yang sudah ada dilewati). Login demo: lihat ringkasan di akhir proses.
 */
class ShowcaseSeeder extends Seeder
{
    private const PASSWORD = 'Demo@12345';

    /** @var array<string,User> */
    private array $users = [];

    /** @var \Illuminate\Support\Collection<string,Department> */
    private $depts;

    public function run(): void
    {
        $this->call([RolePermissionSeeder::class, RegulationSeeder::class, MasterDataSeeder::class]);

        $this->company();
        $this->users();
        $this->depts();
        $employees = $this->employees();            // halaman 1
        $contracts = $this->contracts($employees);  // halaman 2
        $runs = $this->payroll($employees, $contracts); // halaman 3 (+ funding, remittance, bank, temuan)
        $this->inputsAndLoans($employees, $runs);   // halaman 4
        $this->budgets($employees);                 // halaman 5
        $this->integrationMessages();

        $this->command?->info('Data showcase PAYBRIDGE selesai dibuat.');
        $this->command?->table(['Peran', 'Email', 'Kata sandi'], collect($this->users)->map(fn ($u, $role) => [$role, $u->email, self::PASSWORD])->values()->all());
    }

    /* ------------------------------------------------------------------ dasar */

    private function company(): void
    {
        Setting::put('company.name', 'PT Nusantara Makmur Sejahtera');
        Setting::put('company.address', 'Jl. Jenderal Sudirman No. 88, Padang, Sumatera Barat');
        Setting::put('company.npwp', '01.234.567.8-201.000');
    }

    private function users(): void
    {
        $defs = [
            'payroll-admin' => ['Andi Wijaya', 'andi.payroll@contoh.test'],
            'payroll-manager' => ['Sari Dewi Lestari', 'sari.manager@contoh.test'],
            'finance-approver' => ['Bambang Hartono', 'bambang.finance@contoh.test'],
            'direksi' => ['Ir. Hendro Gunawan', 'hendro.direksi@contoh.test'],
            'auditor' => ['Rina Marlina', 'rina.auditor@contoh.test'],
        ];
        foreach ($defs as $role => [$name, $email]) {
            $u = User::where('email', $email)->first();
            if (! $u) {
                $u = User::create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'status' => 'active', 'must_change_password' => false]);
            }
            if (! $u->email_verified_at) {
                $u->forceFill(['email_verified_at' => now()])->save();
            }
            $u->syncRoles([$role]);
            $this->users[$role] = $u;
        }
    }

    private function depts(): void
    {
        $this->depts = collect([['FIN', 'Keuangan'], ['OPS', 'Operasional'], ['HR', 'SDM'], ['IT', 'Teknologi Informasi'], ['MKT', 'Pemasaran']])
            ->mapWithKeys(fn ($d) => [$d[0] => Department::updateOrCreate(['code' => $d[0]], ['name' => $d[1], 'is_active' => true])]);
    }

    /* ------------------------------------------------------- 1. KARYAWAN */

    /** nip => [nama, gender, dept, jabatan, ptkp, npwp?, gaji, grade, bank, rekening, tipe, lembur?, tunj jabatan, join (tahun lalu)] */
    private function employeeRows(): array
    {
        return [
            'EMP-101' => ['Budi Santoso', 'L', 'FIN', 'Staf Akuntansi', 'K/1', true, 8500000, 'STF-1', 'BCA', '8800100101', 'tetap', false, 1500000, 4],
            'EMP-102' => ['Siti Rahmawati', 'P', 'FIN', 'Supervisor Keuangan', 'TK/0', true, 14000000, 'SPV', 'Mandiri', '8800100102', 'tetap', false, 2500000, 6],
            'EMP-103' => ['Agus Pratama', 'L', 'OPS', 'Operator Gudang', 'K/2', true, 6200000, 'STF-1', 'BRI', '8800100103', 'tetap', true, 800000, 3],
            'EMP-104' => ['Dewi Anggraini', 'P', 'OPS', 'Staf Logistik', 'TK/0', true, 6800000, 'STF-1', 'BNI', '8800100104', 'tetap', true, 800000, 2],
            'EMP-105' => ['Rizky Hidayat', 'L', 'IT', 'Software Engineer', 'TK/1', true, 15500000, 'STF-2', 'BCA', '8800100105', 'tetap', false, 2000000, 3],
            'EMP-106' => ['Maya Putri Utami', 'P', 'HR', 'Staf SDM', 'K/0', true, 7500000, 'STF-1', 'Mandiri', '8800100106', 'tetap', false, 1000000, 2],
            'EMP-107' => ['Hendra Wijaya', 'L', 'IT', 'IT Manager', 'K/3', true, 32000000, 'MGR', 'BCA', '8800100107', 'tetap', false, 5000000, 7],
            'EMP-108' => ['Fajar Nugroho', 'L', 'OPS', 'Kepala Operasional', 'K/2', true, 38000000, 'MGR', 'BNI', '8800100108', 'tetap', false, 6000000, 8],
            'EMP-109' => ['Rina Kusuma Dewi', 'P', 'MKT', 'Staf Pemasaran Digital', 'TK/0', true, 8000000, 'STF-1', 'BRI', '8800100109', 'kontrak', false, 1000000, 1],
            'EMP-110' => ['Dr. Andini Prameswari', 'P', 'FIN', 'Direktur Keuangan', 'K/2', true, 95000000, 'DIR', 'Mandiri', '8800100110', 'tetap', false, 25000000, 9],
        ];
    }

    /** @return array<string,Employee> */
    private function employees(): array
    {
        $out = [];
        $i = 0;
        foreach ($this->employeeRows() as $nip => [$name, $g, $dept, $pos, $ptkp, $npwp, $salary, $grade, $bank, $acct, $type, $ot, $tj, $yrs]) {
            $i++;
            $first = strtolower(preg_replace('/[^a-z]/i', '', explode(' ', str_replace('Dr. ', '', $name))[0]));
            $out[$nip] = Employee::updateOrCreate(['nip' => $nip], [
                'full_name' => $name, 'gender' => $g, 'email' => $first.'@nusantaramakmur.test', 'phone' => '0812-3400-'.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT),
                'department_id' => $this->depts[$dept]->id, 'position_title' => $pos, 'employment_type' => $type, 'employment_status' => 'active',
                'join_date' => now()->subYears($yrs)->startOfMonth(), 'contract_end_date' => $type === 'kontrak' ? now()->addMonths(8)->endOfMonth() : null,
                'ptkp_status' => $ptkp, 'npwp' => $npwp ? '09.254.294.3-407.000' : null, 'has_npwp' => $npwp,
                'bank_name' => $bank, 'bank_account_number' => $acct, 'bank_account_hash' => Employee::hashAccount($acct), 'bank_account_name' => $name,
                'bpjs_kesehatan_number' => '000'.(1234567000 + $i), 'bpjs_tk_number' => '25'.(1000000000 + $i * 7),
                'birth_date' => Carbon::create(1985 + ($i % 12), (($i * 3) % 12) + 1, 10 + $i)->toDateString(),
                'sdm_base_salary' => $salary, 'synced_at' => now()->subHours(3 + $i),
            ]);
        }

        // Ringkasan absensi 8 bulan terakhir (ditarik dari SDM pada kondisi sebenarnya)
        foreach ($out as $nip => $emp) {
            for ($k = 8; $k >= 0; $k--) {
                $d = now()->startOfMonth()->subMonths($k);
                if ($k === 0 && now()->day < 20) {
                    continue; // bulan berjalan belum lengkap
                }
                $seed = (int) substr($nip, -3) + $d->month;
                $late = $seed % 4;
                $sick = $seed % 9 === 0 ? 1 : 0;
                $leave = $seed % 7 === 0 ? 1 : 0;
                AttendanceSnapshot::updateOrCreate(['employee_id' => $emp->id, 'year' => $d->year, 'month' => $d->month], [
                    'scheduled_days' => 22, 'present_days' => 22 - $sick - $leave, 'late_count' => $late, 'late_minutes' => $late * 11, 'alpha_days' => 0,
                    'sick_days' => $sick, 'permit_days' => 0, 'paid_leave_days' => $leave, 'unpaid_leave_days' => 0, 'wfh_days' => $seed % 3, 'fetched_at' => $d->copy()->endOfMonth(),
                ]);
            }
        }

        return $out;
    }

    /* -------------------------------------------------------- 2. KONTRAK */

    /**
     * @param  array<string,Employee>  $employees
     * @return array<string,array<int,CompensationContract>> nip => [versi => kontrak]
     */
    private function contracts(array $employees): array
    {
        $comp = fn ($c) => PayComponent::where('code', $c)->first()->id;
        $rows = $this->employeeRows();
        $raises = ['EMP-102' => 15500000, 'EMP-105' => 17500000]; // versi 2 (kenaikan gaji) berlaku sejak 4 bulan lalu
        $raiseDate = now()->startOfMonth()->subMonths(4);
        $out = [];

        foreach ($employees as $nip => $emp) {
            [, , , , , , $salary, $grade, , , $type, $ot, $tj] = $rows[$nip];
            $g = Grade::where('code', $grade)->first();
            $isExec = in_array($grade, ['DIR', 'EXE'], true);
            $tail = (int) substr($nip, -3);
            $comps = [$comp('T_JABATAN') => $tj, $comp('T_TRANSPORT') => 500000, $comp('T_MAKAN') => 600000];

            $existing = CompensationContract::where('employee_id', $emp->id)->where('version', 1)->first();
            if (! $existing) {
                $effective = now()->subYear()->startOfYear();
                $v1 = $this->makeContract($emp, $g, 1, $salary, $effective, $type === 'kontrak' ? 'pkwt' : ($isExec ? 'direksi' : 'pkwtt'), $comps, $ot, 'KTR-SHW-'.$tail.'-1', 'Kontrak awal karyawan', $isExec);
                if (isset($raises[$nip])) {
                    $v1->update(['status' => 'superseded', 'superseded_on' => $raiseDate->copy()->subDay()->toDateString()]);
                }
                $existing = $v1;
                AuditLedger::record('contract.activated', $v1, ['nip' => $nip, 'base_salary' => $salary], $v1->contract_number, $this->users['finance-approver']->id);
            }
            $out[$nip][1] = $existing;

            if (isset($raises[$nip])) {
                $v2 = CompensationContract::where('employee_id', $emp->id)->where('version', 2)->first()
                    ?? $this->makeContract($emp, $g, 2, $raises[$nip], $raiseDate, 'pkwtt', $comps, $ot, 'KTR-SHW-'.$tail.'-2', 'Penyesuaian gaji berkala tahun berjalan', false);
                $out[$nip][2] = $v2;
            }
        }

        // Usulan menunggu persetujuan Keuangan (di luar rentang golongan => butuh Direksi)
        $this->pendingContract($employees['EMP-108'], 2, 42000000, 'verified', 'Usulan kenaikan gaji kepala operasional, di atas batas golongan MGR (maks Rp40 juta)', true);
        // Usulan yang ditolak
        $this->pendingContract($employees['EMP-110'], 2, 110000000, 'rejected', 'Usulan kenaikan direksi', false);

        return $out;
    }

    private function terms(Employee $emp, ?Grade $g, float $salary, Carbon $effective, string $type, bool $ot): array
    {
        return ['employee_id' => $emp->id, 'base_salary' => $salary, 'effective_date' => $effective->toDateString(), 'end_date' => null, 'grade_id' => $g?->id,
            'contract_type' => $type, 'jkk_class' => 1, 'bpjs_kes_enrolled' => true, 'bpjs_tk_enrolled' => true, 'tax_scheme' => 'ter', 'thr_eligible' => true, 'overtime_eligible' => $ot];
    }

    private function makeContract(Employee $emp, ?Grade $g, int $version, float $salary, Carbon $effective, string $type, array $compMap, bool $ot, string $number, string $reason, bool $exec): CompensationContract
    {
        $terms = $this->terms($emp, $g, $salary, $effective, $type, $ot);
        $comps = collect($compMap)->map(fn ($amt, $id) => ['pay_component_id' => $id, 'amount' => $amt])->values()->all();
        $u = $this->users;
        $at = $effective->copy()->subDays(5);

        // dibuat sebagai "proposed" dulu (komponen terkunci begitu kontrak aktif), lalu diaktifkan
        $c = CompensationContract::create($terms + [
            'contract_number' => $number, 'version' => $version, 'status' => 'proposed', 'source' => 'payroll', 'proposed_by' => $u['payroll-admin']->id,
            'proposed_by_name' => $u['payroll-admin']->name, 'reason' => $reason, 'terms_hash' => CompensationContract::hashTerms($terms, $comps),
            'document_ref' => 'DOC/HR/'.$at->format('Y').'/'.str_pad((string) $emp->id, 4, '0', STR_PAD_LEFT), 'created_at' => $at, 'updated_at' => $at,
        ]);
        foreach ($comps as $cc) {
            $c->components()->create($cc);
        }
        $c->update(['status' => 'active', 'activated_at' => $at->copy()->addDays(3), 'verified_by' => $u['payroll-manager']->id, 'verified_at' => $at->copy()->addDay(),
            'verification_note' => 'Dokumen kontrak lengkap & sesuai rentang golongan.', 'approved_by' => $u['finance-approver']->id, 'approved_at' => $at->copy()->addDays(2),
            'second_approved_by' => $exec ? $u['direksi']->id : null, 'second_approved_at' => $exec ? $at->copy()->addDays(3) : null]);

        $steps = [['contracts.verify', 'Verifikasi Payroll', $u['payroll-manager'], $at->copy()->addDay()], ['contracts.approve', 'Persetujuan Keuangan', $u['finance-approver'], $at->copy()->addDays(2)]];
        if ($exec) {
            $steps[] = ['contracts.approve-executive', 'Persetujuan Direksi', $u['direksi'], $at->copy()->addDays(3)];
        }
        foreach ($steps as $i => [$perm, $label, $who, $when]) {
            Approval::create(['approvable_type' => 'CompensationContract', 'approvable_id' => $c->id, 'step' => $i + 1, 'permission' => $perm, 'label' => $label, 'status' => 'approved',
                'acted_by' => $who->id, 'acted_name' => $who->name, 'acted_at' => $when, 'note' => 'Disetujui', 'ip' => '10.10.0.'.(20 + $i)]);
        }

        return $c;
    }

    private function pendingContract(Employee $emp, int $version, float $salary, string $status, string $reason, bool $dual): void
    {
        if (CompensationContract::where('employee_id', $emp->id)->where('version', $version)->exists()) {
            return;
        }
        $u = $this->users;
        $g = Grade::where('code', $emp->position_title === 'Direktur Keuangan' ? 'DIR' : 'MGR')->first();
        $effective = now()->addMonth()->startOfMonth();
        $terms = $this->terms($emp, $g, $salary, $effective, $g->code === 'DIR' ? 'direksi' : 'pkwtt', false);
        $current = CompensationContract::where('employee_id', $emp->id)->where('status', 'active')->first();
        $compRows = $current ? $current->components->map(fn ($c) => ['pay_component_id' => $c->pay_component_id, 'amount' => $c->amount])->all() : [];

        $c = CompensationContract::create($terms + [
            'contract_number' => 'KTR-SHW-'.substr($emp->nip, -3).'-'.$version, 'version' => $version, 'status' => $status, 'source' => 'payroll',
            'proposed_by' => $u['payroll-admin']->id, 'proposed_by_name' => $u['payroll-admin']->name, 'reason' => $reason, 'terms_hash' => CompensationContract::hashTerms($terms, $compRows),
            'verified_by' => $u['payroll-manager']->id, 'verified_at' => now()->subDays(2), 'verification_note' => 'Sudah dicek terhadap rentang golongan.',
            'rejected_by' => $status === 'rejected' ? $u['finance-approver']->id : null, 'rejected_at' => $status === 'rejected' ? now()->subDay() : null,
            'rejection_note' => $status === 'rejected' ? 'Kenaikan melebihi kebijakan tahun berjalan. Ajukan ulang setelah evaluasi kinerja.' : null,
        ]);
        foreach ($compRows as $cc) {
            $c->components()->create($cc);
        }
        Approval::create(['approvable_type' => 'CompensationContract', 'approvable_id' => $c->id, 'step' => 1, 'permission' => 'contracts.verify', 'label' => 'Verifikasi Payroll', 'status' => 'approved',
            'acted_by' => $u['payroll-manager']->id, 'acted_name' => $u['payroll-manager']->name, 'acted_at' => now()->subDays(2), 'note' => 'Disetujui', 'ip' => '10.10.0.21']);
        Approval::create(['approvable_type' => 'CompensationContract', 'approvable_id' => $c->id, 'step' => 2, 'permission' => 'contracts.approve', 'label' => 'Persetujuan Keuangan',
            'status' => $status === 'rejected' ? 'rejected' : 'pending', 'acted_by' => $status === 'rejected' ? $u['finance-approver']->id : null,
            'acted_name' => $status === 'rejected' ? $u['finance-approver']->name : null, 'acted_at' => $status === 'rejected' ? now()->subDay() : null,
            'note' => $status === 'rejected' ? 'Kenaikan melebihi kebijakan tahun berjalan.' : null]);
        if ($dual) {
            Approval::create(['approvable_type' => 'CompensationContract', 'approvable_id' => $c->id, 'step' => 3, 'permission' => 'contracts.approve-executive', 'label' => 'Persetujuan Direksi', 'status' => 'pending']);
        }
    }

    /* ------------------------------------------------ 3. PUTARAN PAYROLL */

    /**
     * @param  array<string,Employee>  $employees
     * @param  array<string,array<int,CompensationContract>>  $contracts
     * @return array<string,PayrollRun> kunci "Y-m" atau "thr"
     */
    private function payroll(array $employees, array $contracts): array
    {
        $regs = app(Regulations::class);
        $bpjsCalc = app(BpjsCalculator::class);
        $pphCalc = app(PphCalculator::class);
        $u = $this->users;
        $rows = $this->employeeRows();
        $current = now()->startOfMonth();
        $created = [];

        // kasbon aktif yang dipotong dari gaji (nip => [pokok, cicilan, jumlah_cicilan, mulai (bulan lalu ke-N)])
        $loanPlan = [
            'EMP-103' => ['KSB-SHW-001', 6000000, 6, 4, 'Biaya sekolah anak'],
            'EMP-106' => ['KSB-SHW-002', 12000000, 12, 6, 'Renovasi rumah'],
            'EMP-109' => ['KSB-SHW-003', 4500000, 5, 2, 'Kebutuhan mendesak keluarga'],
            'EMP-101' => ['KSB-SHW-004', 3000000, 3, 7, 'Servis kendaraan (sudah lunas)'],
        ];

        // bulan yang punya putaran: 7 bulan terakhir + THR + bulan berjalan
        $periods = [];
        for ($k = 7; $k >= 1; $k--) {
            $periods[] = ['type' => 'regular', 'date' => $current->copy()->subMonths($k), 'k' => $k];
        }
        $thrDate = Carbon::create($current->year, 3, 1);
        $hasThr = $thrDate->lt($current) && $thrDate->gte($current->copy()->subMonths(7)->startOfMonth());
        if ($hasThr) {
            $periods[] = ['type' => 'thr', 'date' => $thrDate, 'k' => (int) abs($current->diffInMonths($thrDate))];
        }
        $periods[] = ['type' => 'regular', 'date' => $current->copy(), 'k' => 0];

        $loanModels = [];
        foreach ($loanPlan as $nip => [$no, $principal, $n, $startK, $reason]) {
            $inst = round($principal / $n);
            $start = $current->copy()->subMonths($startK);
            $loanModels[$nip] = EmployeeLoan::firstOrCreate(['loan_number' => $no], [
                'employee_id' => $employees[$nip]->id, 'principal' => $principal, 'installments' => $n, 'installment_amount' => $inst, 'paid_installments' => 0, 'remaining' => $principal,
                'start_year' => $start->year, 'start_month' => $start->month, 'reason' => $reason, 'status' => 'active',
                'requested_by' => $u['payroll-admin']->id, 'approved_by' => $u['finance-approver']->id, 'approved_at' => $start->copy()->subDays(6),
                'created_at' => $start->copy()->subDays(9), 'updated_at' => $start->copy()->subDays(6),
            ]);
        }

        foreach ($periods as $p) {
            /** @var Carbon $d */
            $d = $p['date'];
            $isCurrent = $p['k'] === 0;
            $isThr = $p['type'] === 'thr';
            $prefix = ($isThr ? 'THR' : 'PR').'-'.$d->format('ym');
            $runNumber = $prefix.'-001';
            $existing = PayrollRun::where('run_number', $runNumber)->first()
                ?? (! $isThr ? PayrollRun::where('type', 'regular')->where('period_year', $d->year)->where('period_month', $d->month)->whereNotIn('status', ['cancelled', 'rejected'])->first() : null);
            if ($existing) {
                $created[$isThr ? 'thr' : $d->format('Y-m')] = $existing;

                continue;
            }

            $end = $d->copy()->endOfMonth()->startOfDay();
            $payDate = $isThr ? Carbon::create($d->year, 3, 13) : $end->copy()->subDays(3 > $end->day ? 0 : 3)->startOfDay();
            $status = $isCurrent ? 'verified' : ($p['k'] === 1 ? 'paid' : 'closed');
            $asOf = $end->toDateString();
            $reg = $regs->snapshot($asOf);

            $run = PayrollRun::create([
                'run_number' => $runNumber, 'type' => $p['type'], 'period_year' => $d->year, 'period_month' => $d->month, 'period_start' => $d->toDateString(), 'period_end' => $end->toDateString(),
                'pay_date' => $payDate->toDateString(), 'title' => ($isThr ? 'THR Idulfitri ' : 'Gaji bulanan ').$d->locale('id')->translatedFormat('F Y'),
                'thr_holiday_date' => $isThr ? Carbon::create($d->year, 3, 20)->toDateString() : null, 'status' => 'draft',
                'created_by' => $u['payroll-admin']->id, 'created_at' => $end->copy()->subDays(8), 'updated_at' => $end->copy()->subDays(8),
                'notes' => $isThr ? 'THR dibayar H-7 hari raya sesuai Permenaker 6/2016.' : null,
            ]);

            $paidLoans = [];
            foreach ($employees as $nip => $emp) {
                $ver = (isset($contracts[$nip][2]) && Carbon::parse($contracts[$nip][2]->effective_date)->lte($d)) ? 2 : 1;
                $con = $contracts[$nip][$ver];
                [, , $deptCode, $pos, $ptkp, $hasNpwp, , $gradeCode, $bank, $acct, , $otEligible, $tj] = $rows[$nip];
                $base = (float) $con->base_salary;
                $tail = (int) substr($nip, -3);

                $lines = [];
                $add = function (string $code, string $name, string $type, string $cat, float $amount, bool $taxable, string $gl, bool $bpjsBase = false) use (&$lines) {
                    if (abs($amount) >= 0.005) {
                        $lines[] = ['code' => $code, 'name' => $name, 'type' => $type, 'cat' => $cat, 'amount' => $amount, 'taxable' => $taxable, 'gl' => $gl, 'bpjs_base' => $bpjsBase];
                    }
                };

                $otHours = 0.0;
                if ($isThr) {
                    $months = max(1, min(12, (int) Carbon::parse($emp->join_date)->diffInMonths($d->copy()->addDays(19))));
                    $thr = round($base * min($months, 12) / 12);
                    $add('THR', 'Tunjangan Hari Raya ('.min($months, 12).'/12 bulan)', 'earning', 'thr', $thr, true, 'thr_expense');
                    $fixed = 0.0;
                    $wage = 0.0;
                } else {
                    $add('GAPOK', 'Gaji pokok', 'earning', 'basic', $base, true, 'salary_expense', true);
                    $fixed = 0.0;
                    foreach ($con->components as $cc) {
                        $pc = PayComponent::find($cc->pay_component_id);
                        $add($pc->code, $pc->name, 'earning', 'fixed_allowance', (float) $cc->amount, (bool) $pc->taxable, $pc->gl_key, (bool) $pc->bpjs_base);
                        $fixed += $pc->bpjs_base ? (float) $cc->amount : 0;
                    }
                    $wage = $base + $fixed;

                    // lembur 2 bulan terakhir untuk karyawan operasional yang berhak (sinkron dengan input "applied")
                    if ($otEligible && $p['k'] <= 2 && $p['k'] >= 1) {
                        $otHours = (float) (4 + (($tail + $d->month) % 3) * 2);
                        $pay = round($wage / 173 * (1.5 + 2 * ($otHours - 1)));
                        $add('LEMBUR', 'Lembur ('.$otHours.' jam)', 'earning', 'overtime', $pay, true, 'overtime_expense');
                    }
                    // bonus target kuartal pada putaran bulan lalu
                    if ($p['k'] === 1 && in_array($nip, ['EMP-104', 'EMP-107'], true)) {
                        $add('INP-BONUS', $nip === 'EMP-104' ? 'Bonus target Q3' : 'Bonus penyelesaian proyek ERP', 'earning', 'bonus', $nip === 'EMP-104' ? 5000000 : 12000000, true, 'bonus_expense');
                    }
                }

                // potongan kasbon & koperasi
                $loanDed = 0.0;
                if (! $isThr && isset($loanModels[$nip])) {
                    $L = $loanModels[$nip];
                    $startAt = Carbon::create($L->start_year, $L->start_month, 1);
                    $done = ($paidLoans[$nip] ?? 0) + LoanRepayment::where('loan_id', $L->id)->count();
                    if ($d->gte($startAt) && $done < $L->installments) {
                        $loanDed = (float) $L->installment_amount;
                        $add('KASBON', 'Cicilan kasbon', 'deduction', 'loan', $loanDed, false, 'employee_receivable');
                        $paidLoans[$nip] = ($paidLoans[$nip] ?? 0) + 1;
                        if (! $isCurrent) {
                            LoanRepayment::firstOrCreate(['loan_id' => $L->id, 'payroll_run_id' => $run->id], ['amount' => $loanDed, 'created_at' => $end]);
                        }
                    }
                }
                $coop = (! $isThr && in_array($nip, ['EMP-101', 'EMP-102', 'EMP-106'], true)) ? 50000.0 : 0.0;
                $add('DED_KOPERASI', 'Iuran koperasi', 'deduction', 'deduction', $coop, false, 'other_deduction_payable');

                $earn = collect($lines)->where('type', 'earning');
                $gross = (float) $earn->sum('amount');
                $basic = (float) $earn->where('cat', 'basic')->sum('amount');
                $fixedAll = (float) $earn->where('cat', 'fixed_allowance')->sum('amount');
                $variable = $gross - $basic - $fixedAll;

                // BPJS (kalkulator asli) - tidak dihitung pada putaran THR
                $b = $isThr ? array_fill_keys(['kes_employee', 'kes_company', 'jht_employee', 'jht_company', 'jkk_company', 'jkm_company', 'jp_employee', 'jp_company'], 0.0)
                    : $bpjsCalc->calculate($reg, $wage, 1, true, true);

                // PPh 21 TER
                $taxGross = $gross;
                $pphRes = $pphCalc->monthly($reg, $ptkp, $taxGross, (bool) $hasNpwp);
                $pph = (float) $pphRes['tax'];
                $bpjsEe = $b['kes_employee'] + $b['jht_employee'] + $b['jp_employee'];
                $bpjsEr = $b['kes_company'] + $b['jht_company'] + $b['jkk_company'] + $b['jkm_company'] + $b['jp_company'];
                $ded = (float) collect($lines)->where('type', 'deduction')->sum('amount');
                $net = $gross - $bpjsEe - $pph - $ded;

                $att = AttendanceSnapshot::where('employee_id', $emp->id)->where('year', $d->year)->where('month', $d->month)->first();
                $paid = in_array($status, ['paid', 'closed'], true);

                $item = new PayrollItem([
                    'payroll_run_id' => $run->id, 'employee_id' => $emp->id, 'contract_id' => $con->id, 'contract_terms_hash' => $con->terms_hash, 'department_id' => $emp->department_id, 'grade_code' => $gradeCode,
                    'employee_snapshot' => ['nip' => $nip, 'name' => $emp->full_name, 'position' => $pos, 'department' => $deptCode, 'ptkp' => $ptkp, 'has_npwp' => (bool) $hasNpwp, 'bank' => $bank.' ****'.substr($acct, -4)],
                    'bank_account_hash' => $emp->bank_account_hash,
                    'days_scheduled' => 22, 'days_present' => $att?->present_days ?? 22, 'days_absent' => 0, 'days_unpaid_leave' => 0, 'proration_factor' => 1, 'overtime_hours' => $otHours,
                    'lines' => $lines, 'basic_salary' => $basic, 'fixed_allowances' => $fixedAll, 'variable_earnings' => $variable, 'gross_earnings' => $gross,
                    'bpjs_kes_employee' => $b['kes_employee'], 'bpjs_kes_company' => $b['kes_company'], 'bpjs_jht_employee' => $b['jht_employee'], 'bpjs_jht_company' => $b['jht_company'],
                    'bpjs_jkk_company' => $b['jkk_company'], 'bpjs_jkm_company' => $b['jkm_company'], 'bpjs_jp_employee' => $b['jp_employee'], 'bpjs_jp_company' => $b['jp_company'],
                    'tax_gross' => $taxGross, 'pph21' => $pph, 'pph21_method' => 'ter', 'ter_category' => $pphRes['category'], 'ter_rate' => $pphRes['rate'],
                    'tax_detail' => ['method' => 'TER PMK 168/2023', 'category' => $pphRes['category'], 'rate_pct' => $pphRes['rate'], 'npwp_surcharge' => $pphRes['surcharge']],
                    'loan_deduction' => $loanDed, 'other_deductions' => $coop, 'total_employee_deductions' => $bpjsEe + $pph + $ded, 'net_pay' => $net, 'employer_cost' => $gross + $bpjsEr,
                    'is_held' => false, 'item_hash' => 'pending', 'status' => $paid ? 'paid' : 'calculated',
                    'paid_at' => $paid ? $payDate->copy()->setTime(14, 5) : null, 'payment_ref' => $paid ? 'TRF-'.$d->format('ym').'-'.str_pad((string) $emp->id, 4, '0', STR_PAD_LEFT) : null,
                    'payslip_published_at' => $paid ? $payDate->copy()->setTime(15, 0) : null, 'payslip_emailed_at' => $paid ? $payDate->copy()->setTime(15, 10) : null, 'payslip_email_status' => $paid ? 'terkirim' : null,
                ]);
                $item->save();
                $item->update(['item_hash' => $item->computeHash()]);
            }

            // total putaran dihitung dari item (identik dengan PayrollRunService::refreshTotals)
            $this->refreshTotals($run);
            $run = $run->fresh();

            // temuan, persetujuan, penguncian
            $stamp = $end->copy()->subDays(5);
            $common = ['calculated_by' => $u['payroll-admin']->id, 'calculated_at' => $stamp, 'submitted_by' => $u['payroll-admin']->id, 'submitted_at' => $stamp->copy()->addHours(3),
                'verified_by' => $u['payroll-manager']->id, 'verified_at' => $stamp->copy()->addDay(), 'verification_note' => 'Total sesuai input & absensi SDM, tidak ada temuan kritis.'];
            if ($isCurrent) {
                $run->update($common + ['status' => 'verified', 'budget_check' => ['ok' => true, 'warnings' => 1, 'message' => 'Anggaran Operasional bulan ini terlampaui 3,1%.'],
                    'calc_meta' => ['sdm_synced' => true, 'regulation_as_of' => $asOf, 'sdm_attendance_rows' => count($employees)]]);
                foreach ([['runs.verify', 'Verifikasi Payroll', 'approved', $u['payroll-manager']], ['runs.approve', 'Persetujuan Keuangan', 'pending', null]] as $i => [$perm, $label, $st, $who]) {
                    Approval::create(['approvable_type' => 'PayrollRun', 'approvable_id' => $run->id, 'step' => $i + 1, 'permission' => $perm, 'label' => $label, 'status' => $st,
                        'acted_by' => $who?->id, 'acted_name' => $who?->name, 'acted_at' => $who ? $common['verified_at'] : null, 'note' => $who ? 'Diverifikasi' : null, 'ip' => $who ? '10.10.0.21' : null]);
                }
                $this->findings($run, $employees);
                AuditLedger::record('run.calculated', $run, ['employees' => $run->employee_count, 'net' => $run->total_net], $run->run_number, $u['payroll-admin']->id);
            } else {
                $approvedAt = $stamp->copy()->addDays(2);
                $lock = [
                    'status' => $status, 'locked_at' => $approvedAt, 'snapshot_hash' => PayrollIntegrity::snapshotHash($run), 'approved_by' => $u['finance-approver']->id, 'approved_at' => $approvedAt,
                    'requires_dual_approval' => false, 'paid_at' => $payDate->copy()->setTime(14, 5), 'closed_at' => $status === 'closed' ? $payDate->copy()->addDays(5) : null,
                    'budget_check' => ['ok' => true, 'warnings' => 0], 'calc_meta' => ['sdm_synced' => true, 'regulation_as_of' => $asOf],
                ];
                $run->update($common + $lock);
                foreach ([['runs.verify', 'Verifikasi Payroll', $u['payroll-manager'], $common['verified_at']], ['runs.approve', 'Persetujuan Keuangan', $u['finance-approver'], $approvedAt]] as $i => [$perm, $label, $who, $when]) {
                    Approval::create(['approvable_type' => 'PayrollRun', 'approvable_id' => $run->id, 'step' => $i + 1, 'permission' => $perm, 'label' => $label, 'status' => 'approved',
                        'acted_by' => $who->id, 'acted_name' => $who->name, 'acted_at' => $when, 'note' => 'Disetujui', 'ip' => '10.10.0.2'.$i]);
                }
                $this->settlement($run->fresh(), $employees, $status);
                AuditLedger::record('run.approved', $run, ['net' => $run->total_net, 'snapshot' => $lock['snapshot_hash']], $run->run_number, $u['finance-approver']->id);
                if ($status === 'paid') {
                    AuditLedger::record('bank.reconciled', $run, ['total' => $run->total_disbursable], $run->run_number, $u['payroll-manager']->id);
                }
            }
            $created[$isThr ? 'thr' : $d->format('Y-m')] = $run->fresh();
        }

        // sinkronkan saldo kasbon dari riwayat potongan
        foreach ($loanModels as $nip => $L) {
            $n = LoanRepayment::where('loan_id', $L->id)->count();
            $L->update(['paid_installments' => $n, 'remaining' => max(0, $L->principal - $n * $L->installment_amount), 'status' => $n >= $L->installments ? 'settled' : 'active']);
        }

        return $created;
    }

    private function refreshTotals(PayrollRun $run): void
    {
        $items = $run->items()->get();
        $byGl = [];
        $byDept = [];
        foreach ($items as $i) {
            foreach ($i->lines as $l) {
                $byGl[$l['gl']] = ($byGl[$l['gl']] ?? 0) + (($l['type'] === 'earning') ? $l['amount'] : 0);
            }
            $d = (int) $i->department_id;
            $byDept[$d]['count'] = ($byDept[$d]['count'] ?? 0) + 1;
            $byDept[$d]['gross'] = ($byDept[$d]['gross'] ?? 0) + $i->gross_earnings;
            $byDept[$d]['net'] = ($byDept[$d]['net'] ?? 0) + $i->net_pay;
            $byDept[$d]['employer_cost'] = ($byDept[$d]['employer_cost'] ?? 0) + $i->employer_cost;
        }
        $sum = fn (string $col) => (float) $items->sum($col);
        $bpjsEe = $sum('bpjs_kes_employee') + $sum('bpjs_jht_employee') + $sum('bpjs_jp_employee');
        $bpjsEr = $sum('bpjs_kes_company') + $sum('bpjs_jht_company') + $sum('bpjs_jkk_company') + $sum('bpjs_jkm_company') + $sum('bpjs_jp_company');

        $run->update([
            'employee_count' => $items->count(), 'total_gross' => $sum('gross_earnings'), 'total_employer_bpjs' => $bpjsEr, 'total_employee_bpjs' => $bpjsEe,
            'total_pph21' => $sum('pph21'), 'total_other_deductions' => $sum('other_deductions') + $sum('loan_deduction'), 'total_net' => $sum('net_pay'),
            'total_disbursable' => (float) $items->where('is_held', false)->sum('net_pay'), 'total_employer_cost' => $sum('employer_cost'),
            'totals' => [
                'by_gl' => $byGl, 'by_department' => $byDept,
                'bpjs' => ['kes_employee' => $sum('bpjs_kes_employee'), 'kes_company' => $sum('bpjs_kes_company'), 'jht_employee' => $sum('bpjs_jht_employee'), 'jht_company' => $sum('bpjs_jht_company'),
                    'jkk_company' => $sum('bpjs_jkk_company'), 'jkm_company' => $sum('bpjs_jkm_company'), 'jp_employee' => $sum('bpjs_jp_employee'), 'jp_company' => $sum('bpjs_jp_company')],
                'loan' => $sum('loan_deduction'), 'other_deductions' => $sum('other_deductions'),
            ],
        ]);
    }

    /** Pengajuan dana, batch transfer bank, setoran PPh/BPJS untuk putaran yang sudah dibayar. */
    private function settlement(PayrollRun $run, array $employees, string $status): void
    {
        $u = $this->users;
        $t = $run->totals;
        $b = $t['bpjs'];
        $kesCo = (float) $b['kes_company'];
        $tkCo = (float) ($b['jht_company'] + $b['jkk_company'] + $b['jkm_company'] + $b['jp_company']);
        $debit = [];
        foreach ($t['by_gl'] as $gl => $amt) {
            if (abs($amt) > 0.005) {
                $debit[$gl] = round((float) $amt, 2);
            }
        }
        if ($kesCo > 0) {
            $debit['bpjs_kes_company_expense'] = round($kesCo, 2);
        }
        if ($tkCo > 0) {
            $debit['bpjs_tk_company_expense'] = round($tkCo, 2);
        }
        $credit = array_filter([
            'net_pay_liability' => round((float) $run->total_net, 2), 'pph21_liability' => round((float) $run->total_pph21, 2),
            'bpjs_kes_liability' => round((float) ($b['kes_employee'] + $kesCo), 2), 'bpjs_tk_liability' => round((float) ($b['jht_employee'] + $b['jp_employee']) + $tkCo, 2),
            'employee_receivable' => round((float) ($t['loan'] ?? 0), 2), 'other_deduction_payable' => round((float) ($t['other_deductions'] ?? 0), 2),
        ], fn ($v) => abs($v) > 0.005);

        $payAt = $run->pay_date->copy();
        $fundAt = $payAt->copy()->subDays(2)->setTime(10, 30);
        $f = FundingRequest::create([
            'number' => 'PFD-'.$run->run_number, 'payroll_run_id' => $run->id, 'status' => 'confirmed',
            'breakdown' => ['debit' => $debit, 'credit' => $credit, 'meta' => ['employee_count' => $run->employee_count, 'gross' => $run->total_gross, 'employer_cost' => $run->total_employer_cost]],
            'amount_net_total' => $run->total_net, 'amount_disbursable' => $run->total_disbursable, 'amount_approved' => $run->total_disbursable,
            'finance_ref' => 'MKP-APP-'.$run->run_number, 'decided_by_name' => $u['finance-approver']->name, 'decided_at' => $fundAt, 'decision_note' => 'Dana disetujui sesuai total transfer.',
            'disbursed_amount' => $run->total_disbursable, 'disbursed_at' => $fundAt->copy()->addHours(3), 'disbursed_by_name' => 'Kasir Keuangan', 'disbursement_ref' => 'DSB-'.$run->run_number,
            'journal_refs' => ['accrual' => 'JV-'.$run->run_number.'-A', 'cash' => 'JV-'.$run->run_number.'-C'], 'delivered_at' => $fundAt,
            'created_at' => $fundAt->copy()->subDay(), 'updated_at' => $fundAt->copy()->addDay(),
        ]);

        // file CSV transfer (sama formatnya dengan BankFileService) agar tombol unduh berfungsi
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, ['no', 'nip', 'nama_rekening', 'bank', 'no_rekening', 'nominal', 'berita', 'ref']);
        $n = 0;
        foreach ($run->items()->with('employee')->orderBy('id')->get() as $i) {
            $e = $i->employee;
            fputcsv($fh, [++$n, $e->nip, $e->bank_account_name ?: $e->full_name, $e->bank_name, preg_replace('/\D+/', '', $e->bank_account_number), number_format($i->net_pay, 2, '.', ''), 'Gaji '.$run->periodLabel(), $i->id]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);
        $number = 'BNK-'.$run->run_number.'-1';
        $path = 'disbursement/'.$number.'.csv';
        Storage::disk('local')->put($path, $csv);
        DisbursementBatch::create(['payroll_run_id' => $run->id, 'batch_number' => $number, 'bank_code' => 'ALL', 'item_count' => $n, 'total_amount' => $run->total_disbursable,
            'file_path' => $path, 'file_sha256' => hash('sha256', $csv), 'status' => 'reconciled', 'generated_by' => $u['payroll-admin']->id, 'generated_at' => $payAt->copy()->setTime(9, 0),
            'reconciled_by' => $u['payroll-manager']->id, 'reconciled_at' => $payAt->copy()->setTime(16, 30), 'reconciliation' => ['matched' => $n, 'mismatch' => [], 'note' => 'Seluruh nominal cocok dengan laporan bank.']]);

        // setoran pajak & BPJS
        if ($run->type === 'thr') {
            $amounts = ['pph21' => [(float) $run->total_pph21, 10, 20]];
        } else {
            $amounts = [
                'pph21' => [(float) $run->total_pph21, 10, 20],
                'bpjs_kes' => [(float) ($b['kes_employee'] + $b['kes_company']), 10, null],
                'bpjs_tk' => [(float) ($b['jht_employee'] + $b['jht_company'] + $b['jkk_company'] + $b['jkm_company'] + $b['jp_employee'] + $b['jp_company']), 15, null],
            ];
        }
        $next = Carbon::create($run->period_year, $run->period_month, 1)->addMonth();
        foreach ($amounts as $type => [$amt, $payDay, $reportDay]) {
            if ($amt <= 0.005) {
                continue;
            }
            $due = $next->copy()->day(min($payDay, $next->daysInMonth));
            $isPaid = $due->lt(now()->subDays(2)) || $status === 'closed';
            Remittance::create(['payroll_run_id' => $run->id, 'type' => $type, 'period_year' => $run->period_year, 'period_month' => $run->period_month, 'amount' => $amt,
                'due_date' => $due->toDateString(), 'report_due_date' => $reportDay ? $next->copy()->day(min($reportDay, $next->daysInMonth))->toDateString() : null,
                'status' => $isPaid ? 'paid' : 'pending', 'paid_at' => $isPaid ? $due->copy()->subDays(2)->setTime(11, 0) : null, 'paid_by_name' => $isPaid ? 'Kasir Keuangan' : null,
                'payment_ref' => $isPaid ? ($type === 'pph21' ? 'NTPN-'.strtoupper(substr(md5($run->run_number.$type), 0, 16)) : 'BYR-'.strtoupper(substr(md5($run->run_number.$type), 0, 10))) : null,
                'finance_ref' => $isPaid ? 'MKP-REM-'.$run->run_number.'-'.$type : null, 'journal_ref' => $isPaid ? 'JV-REM-'.$run->run_number.'-'.$type : null]);
        }
    }

    private function findings(PayrollRun $run, array $employees): void
    {
        $u = $this->users;
        $defs = [
            ['BUDGET_OVER', 'warning', false, 'Anggaran departemen Operasional terlampaui', 'Realisasi Operasional bulan ini 3,1% di atas rencana anggaran aktif. Periksa lembur Agus Pratama & Dewi Anggraini.', null, 'open'],
            ['BANK_RECENT', 'info', false, 'Rekening karyawan baru diubah dalam 30 hari terakhir', 'Rizky Hidayat mengganti rekening 12 hari lalu. Konfirmasi ke SDM sebelum transfer.', $employees['EMP-105']->id, 'resolved'],
            ['SALARY_JUMP', 'info', false, 'Kenaikan gaji pokok dibanding bulan lalu', 'Tidak ada lonjakan di atas 15% pada putaran ini.', null, 'resolved'],
        ];
        foreach ($defs as [$code, $sev, $blocking, $title, $detail, $empId, $st]) {
            PayrollFinding::create(['payroll_run_id' => $run->id, 'employee_id' => $empId, 'code' => $code, 'severity' => $sev, 'is_blocking' => $blocking, 'title' => $title, 'detail' => $detail,
                'context' => ['source' => 'showcase'], 'status' => $st, 'resolved_by' => $st === 'resolved' ? $u['payroll-manager']->id : null, 'resolved_at' => $st === 'resolved' ? now()->subDay() : null,
                'resolution_note' => $st === 'resolved' ? 'Sudah dikonfirmasi ke SDM, data valid.' : null, 'fingerprint' => hash('sha256', $run->run_number.$code)]);
        }
    }

    /* ---------------------------------------- 4. INPUT VARIABEL & KASBON */

    private function inputsAndLoans(array $employees, array $runs): void
    {
        $u = $this->users;
        $cur = now()->startOfMonth();
        $prev = $cur->copy()->subMonth();
        $prev2 = $cur->copy()->subMonths(2);
        $runPrev = $runs[$prev->format('Y-m')] ?? null;
        $runPrev2 = $runs[$prev2->format('Y-m')] ?? null;

        if (! PayrollInput::where('description', 'like', '[SHW]%')->exists()) {
            $mk = function (string $nip, string $type, Carbon $period, array $extra) use ($employees, $u) {
                PayrollInput::create(array_merge([
                    'employee_id' => $employees[$nip]->id, 'type' => $type, 'period_year' => $period->year, 'period_month' => $period->month, 'amount' => 0,
                    'requested_by' => $u['payroll-admin']->id, 'created_at' => $period->copy()->addDays(10), 'updated_at' => $period->copy()->addDays(10),
                ], $extra));
            };
            $ok = fn (string $m) => ['ok' => true, 'message' => $m];

            // sudah diterapkan ke putaran lalu (sinkron dengan baris lembur/bonus di slip)
            foreach (['EMP-103' => 103, 'EMP-104' => 104] as $nip => $tail) {
                foreach ([[$prev2, $runPrev2], [$prev, $runPrev]] as [$per, $rn]) {
                    $hours = (float) (4 + (($tail + $per->month) % 3) * 2);
                    $mk($nip, 'overtime', $per, ['work_date' => $per->copy()->addDays(8)->toDateString(), 'day_type' => 'weekday', 'hours' => $hours, 'description' => '[SHW] SPL lembur bongkar muat & stok opname',
                        'amount' => 0, 'attendance_check' => $ok('Sesuai absensi SDM (tercatat '.$hours.' jam).'), 'status' => 'applied', 'approved_by' => $u['payroll-manager']->id,
                        'approved_at' => $per->copy()->addDays(12), 'approval_note' => 'Sesuai SPL', 'applied_run_id' => $rn?->id]);
                }
            }
            $mk('EMP-104', 'bonus', $prev, ['description' => '[SHW] Bonus target Q3', 'amount' => 5000000, 'status' => 'applied', 'approved_by' => $u['finance-approver']->id, 'approved_at' => $prev->copy()->addDays(14), 'approval_note' => 'Target penjualan tercapai 108%', 'applied_run_id' => $runPrev?->id]);
            $mk('EMP-107', 'bonus', $prev, ['description' => '[SHW] Bonus penyelesaian proyek ERP', 'amount' => 12000000, 'status' => 'applied', 'approved_by' => $u['finance-approver']->id, 'approved_at' => $prev->copy()->addDays(14), 'approval_note' => 'Go-live sesuai jadwal', 'applied_run_id' => $runPrev?->id]);

            // bulan berjalan: menunggu keputusan (menunjukkan alur persetujuan)
            $mk('EMP-103', 'overtime', $cur, ['work_date' => $cur->copy()->addDays(2)->toDateString(), 'day_type' => 'weekday', 'hours' => 3, 'description' => '[SHW] SPL-101 bongkar muat kontainer', 'attendance_check' => $ok('Sesuai absensi SDM (tercatat 3 jam).'), 'status' => 'approved', 'approved_by' => $u['payroll-manager']->id, 'approved_at' => now()->subDays(1), 'approval_note' => 'Sesuai SPL']);
            $mk('EMP-104', 'overtime', $cur, ['work_date' => $cur->copy()->addDays(1)->toDateString(), 'day_type' => 'restday', 'hours' => 5, 'description' => '[SHW] SPL-102 pengiriman akhir pekan', 'attendance_check' => ['ok' => true, 'message' => 'Sesuai absensi SDM (tercatat 5 jam).'], 'status' => 'pending']);
            $mk('EMP-105', 'allowance', $cur, ['description' => '[SHW] Tunjangan on-call rilis sistem', 'amount' => 1500000, 'status' => 'pending']);
            $mk('EMP-109', 'reimbursement', $cur, ['description' => '[SHW] Penggantian biaya iklan digital (bukti terlampir)', 'amount' => 2350000, 'evidence_path' => null, 'status' => 'pending']);
            $mk('EMP-102', 'correction', $cur, ['description' => '[SHW] Koreksi selisih lembur bulan lalu', 'amount' => 450000, 'status' => 'approved', 'approved_by' => $u['finance-approver']->id, 'approved_at' => now()->subHours(20), 'approval_note' => 'Dikonfirmasi absensi']);
            $mk('EMP-103', 'overtime', $cur, ['work_date' => $cur->copy()->addDays(4)->toDateString(), 'day_type' => 'weekday', 'hours' => 9, 'description' => '[SHW] SPL-103 lembur tanpa SPL tertulis', 'attendance_check' => ['ok' => false, 'message' => 'Absensi SDM hanya mencatat 2 jam.'], 'status' => 'rejected', 'approved_by' => $u['payroll-manager']->id, 'approved_at' => now()->subHours(30), 'approval_note' => 'Jam lembur tidak sesuai absensi']);
        }

        // kasbon tambahan: 1 lunas (dari putaran lama), 2 menunggu persetujuan, 1 ditolak, 1 dibatalkan
        $extra = [
            ['KSB-SHW-005', 'EMP-102', 8000000, 8, 'pending', 'Uang muka pendidikan S2', $cur->copy()->addMonth()],
            ['KSB-SHW-006', 'EMP-107', 15000000, 10, 'pending', 'Dana darurat keluarga', $cur->copy()->addMonth()],
            ['KSB-SHW-007', 'EMP-110', 50000000, 12, 'rejected', 'Melebihi batas 50% gaji bersih', $cur->copy()->subMonths(2)],
            ['KSB-SHW-008', 'EMP-105', 5000000, 5, 'cancelled', 'Dibatalkan pengaju (sudah ada dana lain)', $cur->copy()->subMonths(3)],
        ];
        foreach ($extra as [$no, $nip, $principal, $n, $st, $reason, $start]) {
            EmployeeLoan::firstOrCreate(['loan_number' => $no], [
                'employee_id' => $employees[$nip]->id, 'principal' => $principal, 'installments' => $n, 'installment_amount' => round($principal / $n), 'paid_installments' => 0, 'remaining' => $principal,
                'start_year' => $start->year, 'start_month' => $start->month, 'reason' => $reason, 'status' => $st, 'requested_by' => $u['payroll-admin']->id,
                'approved_by' => in_array($st, ['rejected'], true) ? $u['finance-approver']->id : null, 'approved_at' => $st === 'rejected' ? now()->subMonths(2) : null,
                'created_at' => $start->copy()->subDays(7), 'updated_at' => $start->copy()->subDays(4),
            ]);
        }
    }

    /* ------------------------------------------------------ 5. ANGGARAN */

    private function budgets(array $employees): void
    {
        $u = $this->users;
        $year = now()->year;
        $rows = $this->employeeRows();

        // dasar anggaran per departemen dari kontrak aktif (bulanan)
        $base = [];
        foreach ($rows as $nip => [, , $dept, , , , $salary, , , , , $ot, $tj]) {
            $base[$dept]['basic'] = ($base[$dept]['basic'] ?? 0) + $salary;
            $base[$dept]['allowance'] = ($base[$dept]['allowance'] ?? 0) + $tj + 1100000;
            $base[$dept]['heads'] = ($base[$dept]['heads'] ?? 0) + 1;
            $base[$dept]['ot'] = ($base[$dept]['ot'] ?? 0) + ($ot ? 1800000 : 300000);
        }

        $plans = [
            // tahun, versi, nama, status, faktor, catatan
            [$year - 1, 1, 'Anggaran SDM '.($year - 1), 'superseded', 0.91, 'Anggaran tahun lalu (arsip).'],
            [$year, 1, 'Anggaran SDM '.$year.' - usulan awal', 'rejected', 0.96, 'Ditolak: belum memasukkan kenaikan UMP dan rekrutmen 3 posisi IT.'],
            [$year, 2, 'Anggaran SDM '.$year.' - revisi disetujui', 'active', 1.04, 'Revisi memasukkan kenaikan gaji berkala, THR, dan bonus kinerja.'],
            [$year + 1, 1, 'Anggaran SDM '.($year + 1).' - draf perencanaan', 'draft', 1.10, 'Draf awal, menunggu masukan kepala departemen.'],
        ];

        foreach ($plans as [$fy, $ver, $name, $status, $factor, $notes]) {
            if (BudgetPlan::where('fiscal_year', $fy)->where('version', $ver)->exists()) {
                continue;
            }
            $plan = BudgetPlan::create([
                'fiscal_year' => $fy, 'version' => $ver, 'name' => $name, 'status' => 'draft', 'notes' => $notes, 'created_by' => $u['payroll-admin']->id,
                'created_at' => Carbon::create($fy, 1, 1)->subMonths(2), 'updated_at' => Carbon::create($fy, 1, 1)->subMonths(2),
            ]);

            $lines = [];
            $total = 0.0;
            foreach ($this->depts as $code => $dept) {
                $b = $base[$code] ?? ['basic' => 0, 'allowance' => 0, 'heads' => 0, 'ot' => 0];
                if ($b['heads'] === 0) {
                    continue;
                }
                // Operasional sengaja dianggarkan lebih ketat pada rencana aktif agar halaman "Anggaran vs Realisasi" menampilkan peringatan
                $f = ($code === 'OPS' && $status === 'active') ? $factor - 0.06 : $factor;
                for ($m = 1; $m <= 12; $m++) {
                    $cats = [
                        'basic' => $b['basic'] * $f, 'allowance' => $b['allowance'] * $f, 'overtime' => $b['ot'] * $f,
                        'bpjs_company' => ($b['basic'] + $b['allowance'] * 0.7) * 0.0924 * $f,
                    ];
                    if ($m === 3) {
                        $cats['thr'] = $b['basic'] * $f;
                    }
                    if (in_array($m, [6, 12], true)) {
                        $cats['bonus'] = $b['basic'] * 0.5 * $f;
                    }
                    foreach ($cats as $cat => $amt) {
                        $amt = round($amt / 1000) * 1000;
                        $lines[] = ['budget_plan_id' => $plan->id, 'department_id' => $dept->id, 'month' => $m, 'category' => $cat, 'planned_headcount' => $cat === 'basic' ? $b['heads'] : null,
                            'amount' => $amt, 'created_at' => now(), 'updated_at' => now()];
                        $total += $amt;
                    }
                }
            }
            foreach (array_chunk($lines, 200) as $chunk) {
                DB::table('budget_lines')->insert($chunk);
            }

            $submitted = Carbon::create($fy, 1, 1)->subMonths(2)->addDays(12);
            $attrs = ['total_amount' => $total, 'status' => $status];
            if ($status !== 'draft') {
                $attrs += ['submitted_by' => $u['payroll-admin']->id, 'submitted_at' => $submitted];
            }
            if (in_array($status, ['active', 'superseded'], true)) {
                $attrs += ['approved_by' => $u['finance-approver']->id, 'approved_at' => $submitted->copy()->addDays(4), 'activated_at' => $submitted->copy()->addDays(5), 'pushed_to_finance_at' => $submitted->copy()->addDays(5)];
            }
            if ($status === 'rejected') {
                $attrs += ['rejection_note' => 'Belum memasukkan kenaikan UMP dan rekrutmen 3 posisi IT. Mohon revisi.'];
            }
            $plan->update($attrs);

            if ($status !== 'draft') {
                $steps = [['budgets.approve', 'Persetujuan Keuangan', $u['finance-approver']], ['budgets.approve', 'Persetujuan Direksi', $u['direksi']]];
                foreach ($steps as $i => [$perm, $label, $who]) {
                    $acted = $status !== 'rejected' || $i === 0;
                    $st = $status === 'rejected' ? 'rejected' : 'approved';
                    if ($status === 'rejected' && $i === 1) {
                        Approval::create(['approvable_type' => 'BudgetPlan', 'approvable_id' => $plan->id, 'step' => $i + 1, 'permission' => $perm, 'label' => $label, 'status' => 'pending']);

                        continue;
                    }
                    Approval::create(['approvable_type' => 'BudgetPlan', 'approvable_id' => $plan->id, 'step' => $i + 1, 'permission' => $perm, 'label' => $label, 'status' => $st,
                        'acted_by' => $who->id, 'acted_name' => $who->name, 'acted_at' => $submitted->copy()->addDays(2 + $i), 'note' => $st === 'rejected' ? 'Mohon revisi' : 'Disetujui', 'ip' => '10.10.0.3'.$i]);
                }
            }
            AuditLedger::record('budget.'.($status === 'active' ? 'activated' : $status), $plan, ['year' => $fy, 'version' => $ver, 'total' => $total], $plan->name, $u['payroll-admin']->id);
        }
    }

    /* ---------------------------------------------- pesan integrasi MKP */

    private function integrationMessages(): void
    {
        $f = FundingRequest::orderByDesc('id')->take(2)->get();
        if ($f->isEmpty() || IntegrationMessage::where('delivery_id', 'like', 'shw-%')->exists()) {
            return;
        }
        $a = $f[0];
        $msgs = [
            ['out', 'keuangan', $a, 'Pengajuan dana '.$a->number, 'Mohon pencairan dana gaji sebesar Rp'.number_format((float) $a->amount_disbursable, 0, ',', '.').' untuk transfer tanggal gajian.', 'Andi Wijaya', 'shw-out-1'],
            ['in', 'keuangan', $a, 'Dana disetujui — '.$a->number, 'Dana telah disetujui dan dicairkan. Silakan generate file transfer bank.', 'Bambang Hartono (Keuangan)', 'shw-in-1'],
            ['in', 'keuangan', $a, 'Pengingat setoran PPh 21 jatuh tempo tanggal 10', 'Bukti setor (NTPN) akan dicatat otomatis setelah dibayar oleh Keuangan.', 'Sistem MKP', 'shw-in-2'],
        ];
        foreach ($msgs as [$dir, $sys, $fr, $subject, $body, $author, $did]) {
            IntegrationMessage::create(['direction' => $dir, 'system' => $sys, 'funding_request_id' => $fr->id, 'subject' => $subject, 'body' => $body, 'author' => $author, 'delivery_id' => $did,
                'read_at' => $dir === 'in' && $did !== 'shw-in-2' ? now()->subHours(5) : null]);
        }
    }
}
