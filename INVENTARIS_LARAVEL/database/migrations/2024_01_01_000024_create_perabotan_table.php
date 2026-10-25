<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modul: Data Perabotan
        Schema::create('perabotan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->string('merk', 100)->nullable();
            $table->date('tgl_masuk')->nullable();
            $table->string('foto')->nullable();
            $table->integer('stok')->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->string('lokasi', 150)->nullable();
            $table->string('status', 30)->default('Baik');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perabotan');
    }
};
