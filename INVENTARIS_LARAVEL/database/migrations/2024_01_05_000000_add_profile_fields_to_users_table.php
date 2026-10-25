<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto_profil')->nullable()->after('jabatan');
            $table->string('no_telepon', 20)->nullable()->after('email');
            $table->text('alamat')->nullable()->after('no_telepon');
            $table->date('tanggal_lahir')->nullable()->after('alamat');
            $table->text('riwayat_pendidikan')->nullable()->after('tanggal_lahir');
            $table->timestamp('last_login_at')->nullable()->after('riwayat_pendidikan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['foto_profil', 'no_telepon', 'alamat', 'tanggal_lahir', 'riwayat_pendidikan', 'last_login_at']);
        });
    }
};
