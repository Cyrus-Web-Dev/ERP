<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Kendaraan Operasional
        Schema::create('kendaraan', function (Blueprint $table) {
            $table->increments('id_kendaraan');
            $table->string('merk', 100);
            $table->string('plat_nomor', 20)->unique();
            $table->string('foto_kendaraan')->nullable();
            $table->integer('jumlah')->default(1);
            $table->decimal('harga_kendaraan', 15, 2)->default(0);
            $table->decimal('total_keseluruhan', 18, 2)->default(0);
            $table->string('status', 30)->default('tersedia');
            $table->timestamps();
        });

        // Modul: Peminjaman Kendaraan (dipakai oleh menu "izin/data_peminjaman.php")
        Schema::create('peminjaman_kendaraan', function (Blueprint $table) {
            $table->increments('id_peminjaman');
            $table->unsignedInteger('id_kendaraan');
            $table->string('peminjam', 150);
            $table->date('tanggal_pinjam')->nullable();
            $table->date('tanggal_kembali')->nullable();
            $table->string('status', 30)->default('Dipinjam');
            $table->timestamps();

            $table->foreign('id_kendaraan')->references('id_kendaraan')->on('kendaraan')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjaman_kendaraan');
        Schema::dropIfExists('kendaraan');
    }
};
