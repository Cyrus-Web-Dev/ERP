<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Opsional: admin awal dari .env (PROCURA_ADMIN_EMAIL + PROCURA_ADMIN_PASSWORD), supaya bisa login
        // pertama kali tanpa registrasi + OTP email. Password di-hash Argon2id oleh cast "hashed".
        $email = config('procura.admin.email');
        $password = config('procura.admin.password');
        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => config('procura.admin.name'), 'password' => $password, 'email_verified_at' => now()]
            );
            $admin->syncRoles(['admin']);
            $admin->forceFill(['role_label' => 'admin', 'status' => 'active'])->save();
        }

        if (config('procura.seed_demo')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
