<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Role di sistem: admin (tampil sebagai "Manager"), staff, viewer.
 * Hak akses staf diberikan PER ORANG (halaman Kelola Pengguna), bukan lewat role. Aman dijalankan berulang.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = array_merge(array_keys(config('procura.permission_labels')), ['reports.view', 'users.manage']);
        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'admin'])->syncPermissions($permissions);
        Role::firstOrCreate(['name' => 'staff'])->syncPermissions(['reports.view']);
        Role::firstOrCreate(['name' => 'viewer'])->syncPermissions(['reports.view']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
