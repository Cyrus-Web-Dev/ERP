<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Membuat 1 akun Super Admin awal supaya bisa langsung login
     * setelah migrasi, tanpa harus lewat alur register + approval.
     */
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['username' => 'superadmin'],
            [
                'nama_lengkap' => 'Super Admin',
                'password' => Hash::make('password123'),
                'jabatan' => 'Administrator',
                'email' => 'superadmin@inventaris.local',
                'status' => 'aktif',
                'id_role' => 1,
                'level_akses' => 'super_admin',
                'approved' => 1,
                'email_verified' => 1,
                'registration_date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
