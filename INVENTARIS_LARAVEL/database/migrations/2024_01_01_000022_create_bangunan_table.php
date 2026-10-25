<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Data Gedung Perusahaan
        Schema::create('bangunan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode_gedung', 50)->unique();
            $table->string('nama_gedung', 150);
            $table->decimal('harga_gedung', 18, 2)->default(0);
            $table->string('kondisi', 50)->default('Baik');
            $table->string('lokasi', 200)->nullable();
            $table->string('fungsi', 150)->nullable();
            $table->string('foto_gedung')->nullable();
            $table->date('tanggal_berdiri')->nullable();
            $table->string('foto_pengelola')->nullable();
            $table->string('pengelola', 150)->nullable();
            $table->string('jabatan_pengelola', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bangunan');
    }
};
