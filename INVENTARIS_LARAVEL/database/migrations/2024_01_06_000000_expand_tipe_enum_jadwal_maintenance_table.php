<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tipeBaru = ['elektronik', 'non_elektronik', 'perabotan', 'kendaraan', 'gedung'];

    private array $tipeLama = ['kendaraan', 'gedung'];

    public function up(): void
    {
        $this->terapkanEnum($this->tipeBaru);
    }

    public function down(): void
    {
        $this->terapkanEnum($this->tipeLama);
    }

    /**
     * MySQL dan PostgreSQL punya cara berbeda untuk mengubah daftar nilai
     * enum, jadi di-cabang sesuai driver supaya migration ini tetap
     * portabel dan tidak terikat ke satu database saja.
     */
    private function terapkanEnum(array $nilai): void
    {
        $daftar = "'".implode("','", $nilai)."'";
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE jadwal_maintenance DROP CONSTRAINT IF EXISTS jadwal_maintenance_tipe_check');
            DB::statement("ALTER TABLE jadwal_maintenance ADD CONSTRAINT jadwal_maintenance_tipe_check CHECK (tipe IN ({$daftar}))");

            return;
        }

        // Default: MySQL / MariaDB
        DB::statement("ALTER TABLE jadwal_maintenance MODIFY tipe ENUM({$daftar}) NOT NULL");
    }
};
