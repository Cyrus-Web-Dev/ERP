<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();
            $t->string('name');
            $t->string('category', 30)->nullable()->index();
            $t->string('contact_person', 120)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 40)->nullable();
            $t->text('address')->nullable();
            $t->string('npwp', 30)->nullable();
            $t->string('bank_name', 60)->nullable();
            $t->string('bank_account', 40)->nullable();
            $t->string('bank_holder', 120)->nullable();
            $t->unsignedTinyInteger('payment_terms_days')->default(14);
            $t->decimal('rating', 3, 2)->default(0);           // rata-rata skor kinerja (0-5)
            $t->unsignedInteger('rating_count')->default(0);
            $t->string('status', 20)->default('active')->index(); // active | inactive | blacklisted
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('catalog_items', function (Blueprint $t) {
            $t->id();
            $t->string('sku', 40)->unique();
            $t->string('name');
            $t->string('category', 30)->index();
            $t->string('unit', 20)->default('unit');
            $t->decimal('reference_price', 16, 2)->default(0);
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('document_counters', function (Blueprint $t) {
            $t->id();
            $t->string('prefix', 10);
            $t->string('period', 6);            // YYYYMM
            $t->unsignedInteger('last')->default(0);
            $t->unique(['prefix', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('catalog_items');
        Schema::dropIfExists('vendors');
    }
};
