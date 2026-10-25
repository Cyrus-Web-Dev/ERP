<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('username', 100);
            $table->dateTime('waktu');
            $table->boolean('berhasil')->default(false);
            $table->text('user_agent')->nullable();

            $table->index(['ip_address', 'waktu']);
            $table->index('username');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
