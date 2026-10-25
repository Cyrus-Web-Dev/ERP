<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sistem lain (Inventaris, Keuangan, SDM, ...) yang boleh memanggil REST API PROCURA.
        Schema::create('api_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('system', 20)->index();                        // inventaris | keuangan | sdm | lainnya
            $t->string('token_prefix', 12)->index();
            $t->string('token_hash', 64)->unique();                   // SHA-256 dari token; token asli hanya ditampilkan sekali
            $t->json('scopes');
            $t->string('webhook_url', 500)->nullable();
            $t->string('webhook_secret', 80)->nullable();
            $t->json('webhook_events')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_used_at')->nullable();
            $t->string('last_used_ip', 45)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $t->string('event', 60)->index();
            $t->json('payload');
            $t->string('status', 12)->default('pending')->index();   // pending | delivered | failed
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();
        });

        // Kunci idempotensi supaya permintaan API yang diulang tidak membuat data ganda.
        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_client_id')->constrained()->cascadeOnDelete();
            $t->string('key', 100);
            $t->string('resource_type', 40)->nullable();
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['api_client_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('api_clients');
    }
};
