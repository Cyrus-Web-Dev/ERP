<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twofa_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('user_id');
            $table->foreign('user_id')->references('id_user')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twofa_codes');
    }
};
