<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nama tabel & kolom disamakan dengan project PHP native sebelumnya.
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id_role');
            $table->string('nama_role', 50);
        });

        DB::table('roles')->insert([
            ['id_role' => 1, 'nama_role' => 'super_admin'],
            ['id_role' => 2, 'nama_role' => 'admin'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
