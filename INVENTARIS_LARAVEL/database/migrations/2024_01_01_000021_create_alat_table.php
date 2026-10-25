<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Data Barang Non Elektronik
        Schema::create('alat', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama', 150);
            $table->string('jenis', 100);
            $table->date('tanggal_beli')->nullable();
            $table->decimal('harga', 15, 2)->default(0);
            $table->integer('jumlah')->default(0);
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alat');
    }
};
