<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengajuan barang/jasa yang dikirim ke sistem Pengadaan (PROCURA).
        Schema::create('pengajuan_pengadaan', function (Blueprint $t) {
            $t->id();
            $t->string('nomor', 30)->unique();                     // PGJ-2610-0001 (juga dikirim sebagai external_ref)
            $t->string('procura_nomor', 30)->nullable()->index();  // PR-2610-0001 di PROCURA
            $t->string('judul', 200);
            $t->string('kategori', 30);
            $t->string('prioritas', 10)->default('normal');
            $t->date('tanggal_dibutuhkan')->nullable();
            $t->text('alasan')->nullable();
            $t->string('nama_pemohon', 120);
            $t->string('unit_pemohon', 120)->nullable();
            $t->decimal('total_estimasi', 16, 2)->default(0);

            $t->string('status', 20)->default('draft')->index();   // draft | status PROCURA
            $t->string('stasiun', 20)->nullable();
            $t->text('alasan_penolakan')->nullable();
            $t->text('error_terakhir')->nullable();                // kegagalan kirim/sinkron terakhir

            // Serah terima dari Pengadaan
            $t->string('serah_terima_nomor', 30)->nullable();
            $t->string('serah_terima_status', 20)->nullable();     // handed_over | confirmed
            $t->date('serah_terima_tanggal')->nullable();
            $t->string('serah_terima_penerima', 120)->nullable();
            $t->text('serah_terima_catatan')->nullable();
            $t->timestamp('dikonfirmasi_at')->nullable();
            $t->timestamp('aset_dicatat_at')->nullable();

            $t->json('snapshot')->nullable();                      // tanggapan terakhir API (dana, PO, serah terima)
            $t->timestamp('terkirim_at')->nullable();
            $t->timestamp('disinkronkan_at')->nullable();
            $t->unsignedInteger('dibuat_oleh')->nullable()->index();
            $t->timestamps();
        });

        Schema::create('pengajuan_pengadaan_item', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengajuan_id')->constrained('pengajuan_pengadaan')->cascadeOnDelete();
            $t->string('nama', 200);
            $t->text('spesifikasi')->nullable();
            $t->decimal('jumlah', 14, 2);
            $t->string('satuan', 20)->default('unit');
            $t->decimal('harga_estimasi', 16, 2)->default(0);
            $t->timestamps();
        });

        // Buku laporan: kabar masuk dari Pengadaan (webhook / sinkron) dan catatan keluar dari Inventaris.
        Schema::create('laporan_pengadaan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pengajuan_id')->nullable()->constrained('pengajuan_pengadaan')->cascadeOnDelete();
            $t->string('arah', 8);                                 // masuk | keluar
            $t->string('jenis', 30)->index();                      // status | pengiriman | pesanan | penerimaan | serah_terima | catatan | konfirmasi
            $t->string('judul', 200);
            $t->text('isi')->nullable();
            $t->string('kunci', 80)->nullable();                   // penanda anti-duplikat antara webhook & sinkron
            $t->string('delivery_id', 40)->nullable()->unique();   // X-Procura-Delivery (idempotensi webhook)
            $t->timestamp('dibaca_at')->nullable();
            $t->unsignedInteger('dibuat_oleh')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['pengajuan_id', 'created_at']);
            $t->index(['arah', 'dibaca_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_pengadaan');
        Schema::dropIfExists('pengajuan_pengadaan_item');
        Schema::dropIfExists('pengajuan_pengadaan');
    }
};
