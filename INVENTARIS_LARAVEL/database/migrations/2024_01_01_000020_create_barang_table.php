<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Data Barang Elektronik
        Schema::create('barang', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_barang', 150);
            $table->string('kategori_barang', 100);
            $table->integer('jumlah')->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->string('foto_barang')->nullable();
            $table->date('tgl')->nullable();
            $table->string('kode_aset', 50)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barang');
    }
};
