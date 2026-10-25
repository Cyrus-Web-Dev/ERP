<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peminjaman_kendaraan', function (Blueprint $table) {
            $table->string('nik', 20)->nullable()->after('peminjam');
            $table->string('tujuan', 255)->nullable()->after('nik');
        });
    }

    public function down(): void
    {
        Schema::table('peminjaman_kendaraan', function (Blueprint $table) {
            $table->dropColumn(['nik', 'tujuan']);
        });
    }
};
