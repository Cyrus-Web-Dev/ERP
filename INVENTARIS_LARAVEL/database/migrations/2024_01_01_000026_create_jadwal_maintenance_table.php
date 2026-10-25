<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Jadwal Maintenance (Kendaraan & Gedung)
        Schema::create('jadwal_maintenance', function (Blueprint $table) {
            $table->id();
            $table->enum('tipe', ['kendaraan', 'gedung']);
            $table->string('item_id', 50); // id_kendaraan (angka) atau kode_gedung (kode)
            $table->string('item_nama', 150);
            $table->string('jenis_maintenance', 150);
            $table->date('tanggal_jadwal');
            $table->text('catatan')->nullable();
            $table->enum('status', ['Terjadwal', 'Selesai'])->default('Terjadwal');
            $table->string('dibuat_oleh', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('selesai_at')->nullable();

            $table->index(['tipe', 'item_id']);
            $table->index('tanggal_jadwal');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_maintenance');
    }
};
