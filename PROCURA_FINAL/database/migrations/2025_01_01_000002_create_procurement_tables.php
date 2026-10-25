<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Permintaan dari Inventaris (Purchase Request)
        Schema::create('purchase_requests', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('source', 20)->default('internal')->index();   // inventaris | internal | api
            $t->string('external_ref', 60)->nullable()->index();      // nomor permintaan di sistem Inventaris
            $t->string('requester_name', 120);
            $t->string('requester_unit', 120)->nullable();
            $t->string('category', 30)->index();
            $t->string('title');
            $t->text('justification')->nullable();
            $t->string('priority', 10)->default('normal')->index();   // low | normal | high | urgent
            $t->date('needed_date')->nullable();
            $t->string('status', 20)->default('submitted')->index();
            $t->decimal('estimated_total', 16, 2)->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('rejection_reason')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->text('specification')->nullable();
            $t->decimal('quantity', 14, 2);
            $t->string('unit', 20)->default('unit');
            $t->decimal('estimated_price', 16, 2)->default(0);       // harga satuan perkiraan
            $t->timestamps();
        });

        // 2. Penawaran vendor
        Schema::create('quotations', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $t->string('vendor_ref', 60)->nullable();
            $t->date('quoted_date');
            $t->date('valid_until')->nullable();
            $t->unsignedSmallInteger('lead_time_days')->default(7);
            $t->string('payment_terms', 120)->nullable();
            $t->decimal('total', 16, 2)->default(0);
            $t->text('notes')->nullable();
            $t->boolean('is_selected')->default(false);
            $t->text('selection_reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_request_item_id')->constrained()->cascadeOnDelete();
            $t->decimal('unit_price', 16, 2);
            $t->decimal('quantity', 14, 2);
            $t->string('note')->nullable();
        });

        // 3. Pengajuan dana ke Keuangan
        Schema::create('funding_requests', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 16, 2);
            $t->text('purpose');
            $t->string('status', 20)->default('pending_internal')->index();
            // pending_internal | submitted | approved | rejected | revision | disbursed | settled | cancelled
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('submitted_at')->nullable();
            $t->foreignId('internal_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('internal_approved_at')->nullable();
            // keputusan Keuangan
            $t->string('decided_by_name', 120)->nullable();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->decimal('approved_amount', 16, 2)->nullable();
            $t->text('decision_note')->nullable();
            $t->string('finance_ref', 60)->nullable()->index();      // nomor dokumen di sistem Keuangan
            // pencairan
            $t->decimal('disbursed_amount', 16, 2)->nullable();
            $t->timestamp('disbursed_at')->nullable();
            $t->string('disbursement_ref', 60)->nullable();
            $t->string('disbursement_method', 30)->nullable();
            // pertanggungjawaban (LPJ)
            $t->decimal('actual_spent', 16, 2)->nullable();
            $t->decimal('returned_amount', 16, 2)->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->text('settlement_note')->nullable();
            $t->timestamps();
        });

        // 4. Purchase Order
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('funding_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('quotation_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $t->date('order_date');
            $t->date('expected_date')->nullable();
            $t->decimal('subtotal', 16, 2)->default(0);
            $t->decimal('tax_percent', 5, 2)->default(0);
            $t->decimal('tax_amount', 16, 2)->default(0);
            $t->decimal('total', 16, 2)->default(0);
            $t->string('payment_terms', 120)->nullable();
            $t->text('delivery_address')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('draft')->index();      // draft | sent | partial | received | cancelled
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_request_item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('description');
            $t->decimal('quantity', 14, 2);
            $t->decimal('received_quantity', 14, 2)->default(0);
            $t->string('unit', 20)->default('unit');
            $t->decimal('unit_price', 16, 2);
            $t->timestamps();
        });

        // 5. Penerimaan barang / jasa
        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->date('received_date');
            $t->string('delivery_note_no', 60)->nullable();
            $t->string('condition', 20)->default('good');            // good | partial_damage | rejected
            $t->unsignedTinyInteger('vendor_score')->nullable();      // 1-5, dipakai menghitung rating vendor
            $t->text('notes')->nullable();
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('goods_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
            $t->decimal('quantity_received', 14, 2);
            $t->decimal('quantity_rejected', 14, 2)->default(0);
            $t->string('note')->nullable();
        });

        // 6. Pembayaran ke vendor (memakai dana yang dicairkan Keuangan)
        Schema::create('vendor_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('funding_request_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 16, 2);
            $t->date('paid_at');
            $t->string('method', 30)->default('transfer');
            $t->string('reference', 80)->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // 7. Serah terima ke Inventaris
        Schema::create('handovers', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $t->date('handover_date');
            $t->string('receiver_name', 120);
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('handed_over');         // handed_over | confirmed
            $t->timestamp('confirmed_at')->nullable();
            $t->string('confirmed_by_name', 120)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // 8. Buku laporan antar-divisi (Meja Inventaris & Meja Keuangan)
        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->string('counterpart', 20)->index();                   // inventaris | keuangan
            $t->string('direction', 5);                               // in (diterima Pengadaan) | out (dikirim Pengadaan)
            $t->string('kind', 30)->index();
            $t->string('subject');
            $t->text('body')->nullable();
            $t->foreignId('purchase_request_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('funding_request_id')->nullable()->constrained()->cascadeOnDelete();
            $t->boolean('is_auto')->default(false);
            $t->timestamp('read_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // 9. Lini masa tiap permintaan
        Schema::create('request_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $t->string('type', 40);
            $t->string('description');
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('actor_label', 80)->nullable();                // mis. "Sistem Keuangan (API)"
            $t->timestamp('created_at')->useCurrent();
            $t->index(['purchase_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['request_events', 'reports', 'handovers', 'vendor_payments', 'goods_receipt_items', 'goods_receipts', 'purchase_order_items', 'purchase_orders', 'funding_requests', 'quotation_items', 'quotations', 'purchase_request_items', 'purchase_requests'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
    }
};
