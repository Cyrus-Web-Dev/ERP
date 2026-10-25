<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Penggunaan Barang Elektronik (mengurangi stok tabel barang)
        Schema::create('penggunaan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_karyawan', 150);
            $table->string('foto_karyawan')->nullable();
            $table->unsignedInteger('barang_id');
            $table->integer('jumlah')->default(1);
            $table->timestamps();

            $table->foreign('barang_id')->references('id')->on('barang')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penggunaan');
    }
};
