<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id_user');
            $table->string('nama_lengkap', 150);
            $table->string('username', 100)->unique();
            $table->string('password');
            $table->string('jabatan', 100)->nullable();
            $table->string('email', 150)->unique();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->unsignedInteger('id_role')->default(2);
            $table->enum('level_akses', ['super_admin', 'admin'])->default('admin');
            $table->boolean('approved')->default(0);
            $table->boolean('email_verified')->default(0);
            $table->string('reset_token', 100)->nullable();
            $table->timestamp('reset_token_expiry')->nullable();
            $table->timestamp('registration_date')->useCurrent();
            $table->rememberToken();
            $table->timestamps();

            $table->foreign('id_role')->references('id_role')->on('roles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
