<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\FundingRequest;
use App\Models\GoodsReceipt;
use App\Models\Handover;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Report;
use App\Models\RequestEvent;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Support\Presenter;
use App\Support\Workflow;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh aturan alur pengadaan ada di sini (bukan di controller), sehingga halaman web
 * dan REST API memakai aturan yang SAMA:
 *
 *   Inventaris minta -> Pengadaan tinjau -> cari penawaran -> ajukan dana ke Keuangan
 *   -> Keuangan putuskan (setuju / tolak / revisi) -> dana cair -> PO ke vendor
 *   -> barang/jasa diterima -> bayar vendor + LPJ -> serah terima ke Inventaris
 *
 * Setiap langkah: divalidasi -> disimpan dalam transaksi -> dicatat di lini masa & buku laporan
 * (Meja Inventaris / Meja Keuangan) -> webhook ke sistem lain.
 */
class ProcurementService
{
    public function __construct(private WebhookService $webhooks, private VendorMailer $vendorMailer)
    {
    }

    // =====================================================================
    //  1. PERMINTAAN dari Inventaris
    // =====================================================================

    /**
     * @param  array{title:string,category:string,requester_name:string,requester_unit?:?string,justification?:?string,priority?:string,needed_date?:?string,external_ref?:?string,items:array}  $data
     */
    public function createRequest(array $data, string $source, ?User $user = null, ?string $actorLabel = null): PurchaseRequest
    {
        return DB::transaction(function () use ($data, $source, $user, $actorLabel) {
            $pr = PurchaseRequest::create([
                'number' => DocNumber::next('PR'),
                'source' => $source,
                'external_ref' => $data['external_ref'] ?? null,
                'requester_name' => $data['requester_name'],
                'requester_unit' => $data['requester_unit'] ?? null,
                'category' => $data['category'],
                'title' => $data['title'],
                'justification' => $data['justification'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'needed_date' => $data['needed_date'] ?? null,
                'status' => 'submitted',
                'created_by' => $user?->id,
            ]);

            $total = 0;
            foreach ($data['items'] as $row) {
                $pr->items()->create([
                    'catalog_item_id' => $row['catalog_item_id'] ?? null,
                    'name' => $row['name'],
                    'specification' => $row['specification'] ?? null,
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'] ?? 'unit',
                    'estimated_price' => $row['estimated_price'] ?? 0,
                ]);
                $total += (float) $row['quantity'] * (float) ($row['estimated_price'] ?? 0);
            }
            $pr->update(['estimated_total' => $total]);

            $this->event($pr, 'created', "Permintaan diterima dari {$pr->requester_name}".($pr->requester_unit ? " ({$pr->requester_unit})" : ''), $user, $actorLabel);
            $this->report('inventaris', 'in', 'request', "Permintaan baru: {$pr->title}", $this->itemsSummary($pr).($pr->justification ? "\n\nAlasan: {$pr->justification}" : ''), $pr, null, true, $user);
            ActivityLogger::log('request.created', "Permintaan {$pr->number} dibuat: {$pr->title}", $pr, $user?->id);

            return $pr;
        });
    }

    public function startReview(PurchaseRequest $pr, User $user): void
    {
        $this->assertStatus($pr, ['submitted'], 'Hanya permintaan baru yang bisa mulai ditinjau.');

        DB::transaction(function () use ($pr, $user) {
            $pr->update(['status' => 'reviewing', 'handled_by' => $user->id]);
            $this->event($pr, 'reviewing', "Permintaan mulai ditinjau oleh {$user->name}", $user);
            $this->report('inventaris', 'out', 'status_update', "Permintaan {$pr->number} sedang ditinjau", "Tim Pengadaan ({$user->name}) sudah menerima dan mulai meninjau permintaan \"{$pr->title}\".", $pr, null, true, $user);
        });
        $this->statusChanged($pr);
    }

    public function reject(PurchaseRequest $pr, string $reason, User $user): void
    {
        $this->assertStatus($pr, ['submitted', 'reviewing', 'sourcing'], 'Permintaan ini sudah berjalan lebih jauh dan tidak bisa ditolak. Batalkan saja bila perlu.');

        DB::transaction(function () use ($pr, $reason, $user) {
            $pr->update(['status' => 'rejected', 'rejection_reason' => $reason, 'completed_at' => now()]);
            $this->event($pr, 'rejected', "Permintaan ditolak: {$reason}", $user);
            $this->report('inventaris', 'out', 'status_update', "Permintaan {$pr->number} ditolak", "Alasan: {$reason}", $pr, null, true, $user);
        });
        $this->statusChanged($pr);
    }

    public function cancel(PurchaseRequest $pr, string $reason, User $user): void
    {
        if ($pr->isClosed()) {
            throw new WorkflowException('Permintaan ini sudah ditutup.');
        }
        if ($pr->handovers()->exists() || $pr->purchaseOrders()->whereIn('status', ['partial', 'received'])->exists()) {
            throw new WorkflowException('Barang sudah diterima, permintaan tidak bisa dibatalkan.');
        }
        if ($pr->fundingRequests()->where('status', 'disbursed')->exists()) {
            throw new WorkflowException('Dana sudah dicairkan ke Pengadaan. Batalkan PO (bila ada), lalu serahkan LPJ agar dana dikembalikan ke Keuangan, baru batalkan permintaan ini.');
        }

        DB::transaction(function () use ($pr, $reason, $user) {
            $pr->purchaseOrders()->whereIn('status', ['draft', 'sent'])->update(['status' => 'cancelled']);
            $pr->fundingRequests()->whereIn('status', ['pending_internal', 'submitted', 'approved', 'revision'])->update(['status' => 'cancelled']);
            $pr->update(['status' => 'cancelled', 'rejection_reason' => $reason, 'completed_at' => now()]);
            $this->event($pr, 'cancelled', "Permintaan dibatalkan: {$reason}", $user);
            $this->report('inventaris', 'out', 'status_update', "Permintaan {$pr->number} dibatalkan", "Alasan: {$reason}", $pr, null, true, $user);
        });
        $this->statusChanged($pr);
    }

    // =====================================================================
    //  2. PENAWARAN VENDOR
    // =====================================================================

    /** @param array{vendor_id:int,vendor_ref?:?string,quoted_date:string,valid_until?:?string,lead_time_days?:int,payment_terms?:?string,notes?:?string,prices:array<int,float>} $data prices: [pr_item_id => harga satuan] */
    public function addQuotation(PurchaseRequest $pr, array $data, User $user): Quotation
    {
        $this->assertStatus($pr, ['submitted', 'reviewing', 'sourcing', 'funding'], 'Permintaan ini tidak menerima penawaran baru.');

        $vendor = Vendor::findOrFail($data['vendor_id']);
        if (! $vendor->isUsable()) {
            throw new WorkflowException("Vendor {$vendor->name} berstatus tidak aktif/masuk daftar hitam.");
        }
        if ($pr->quotations()->where('vendor_id', $vendor->id)->exists()) {
            throw new WorkflowException("Vendor {$vendor->name} sudah punya penawaran untuk permintaan ini.");
        }

        return DB::transaction(function () use ($pr, $data, $vendor, $user) {
            $q = Quotation::create([
                'number' => DocNumber::next('QT'),
                'purchase_request_id' => $pr->id,
                'vendor_id' => $vendor->id,
                'vendor_ref' => $data['vendor_ref'] ?? null,
                'quoted_date' => $data['quoted_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'lead_time_days' => $data['lead_time_days'] ?? 7,
                'payment_terms' => $data['payment_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $total = 0;
            foreach ($pr->items as $item) {
                $price = (float) ($data['prices'][$item->id] ?? 0);
                if ($price <= 0) {
                    throw new WorkflowException("Harga untuk \"{$item->name}\" belum diisi.");
                }
                $q->items()->create(['purchase_request_item_id' => $item->id, 'unit_price' => $price, 'quantity' => $item->quantity]);
                $total += $price * $item->quantity;
            }
            $q->update(['total' => $total]);

            if (in_array($pr->status, ['submitted', 'reviewing'], true)) {
                $pr->update(['status' => 'sourcing', 'handled_by' => $pr->handled_by ?? $user->id]);
            }
            $this->event($pr, 'quotation', "Penawaran {$vendor->name} dicatat: Rp ".number_format($total, 0, ',', '.'), $user);

            return $q;
        });
    }

    public function selectQuotation(PurchaseRequest $pr, Quotation $q, string $reason, User $user): void
    {
        $this->assertStatus($pr, ['sourcing', 'reviewing'], 'Penawaran hanya bisa dipilih saat tahap pencarian penawaran.');
        if ($q->purchase_request_id !== $pr->id) {
            throw new WorkflowException('Penawaran tidak sesuai dengan permintaan ini.');
        }

        DB::transaction(function () use ($pr, $q, $reason, $user) {
            $pr->quotations()->update(['is_selected' => false, 'selection_reason' => null]);
            $q->update(['is_selected' => true, 'selection_reason' => $reason]);
            $this->event($pr, 'quotation_selected', "Penawaran {$q->vendor->name} dipilih: {$reason}", $user);
        });
    }

    // =====================================================================
    //  3. PENGAJUAN DANA -> KEUANGAN
    // =====================================================================

    public function suggestedFundingAmount(PurchaseRequest $pr): float
    {
        $q = $pr->quotations()->where('is_selected', true)->first();
        $base = $q ? (float) $q->total : (float) $pr->estimated_total;

        return round($base * (1 + (float) config('procura.default_tax_percent') / 100), 2);
    }

    public function submitFunding(PurchaseRequest $pr, float $amount, string $purpose, User $user): FundingRequest
    {
        $this->assertStatus($pr, ['sourcing', 'funding'], 'Dana hanya bisa diajukan setelah penawaran dipilih (tahap cari penawaran).');

        $quotation = $pr->quotations()->where('is_selected', true)->first();
        if (! $quotation) {
            throw new WorkflowException('Pilih dulu satu penawaran vendor sebelum mengajukan dana ke Keuangan.');
        }
        if ($pr->fundingRequests()->whereIn('status', ['pending_internal', 'submitted', 'approved', 'revision', 'disbursed'])->exists()) {
            throw new WorkflowException('Permintaan ini sudah punya pengajuan dana yang masih berjalan.');
        }

        return DB::transaction(function () use ($pr, $amount, $purpose, $user, $quotation) {
            $needsInternal = $amount >= (float) config('procura.internal_approval_threshold') && ! $user->can('funding.approve');

            $f = FundingRequest::create([
                'number' => DocNumber::next('FR'),
                'purchase_request_id' => $pr->id,
                'quotation_id' => $quotation->id,
                'amount' => $amount,
                'purpose' => $purpose,
                'status' => $needsInternal ? 'pending_internal' : 'submitted',
                'submitted_by' => $user->id,
                'submitted_at' => $needsInternal ? null : now(),
                'internal_approved_by' => $needsInternal ? null : $user->id,
                'internal_approved_at' => $needsInternal ? null : now(),
            ]);
            $pr->update(['status' => 'funding']);

            if ($needsInternal) {
                $this->event($pr, 'funding_pending', "Pengajuan dana {$f->number} (Rp ".number_format($amount, 0, ',', '.').") menunggu persetujuan internal Manager", $user);
            } else {
                $this->announceFundingSubmitted($f, $user);
            }
            $this->syncStatusOnly($pr);

            return $f;
        });
    }

    public function approveInternal(FundingRequest $f, User $user): void
    {
        if ($f->status !== 'pending_internal') {
            throw new WorkflowException('Pengajuan ini tidak menunggu persetujuan internal.');
        }

        DB::transaction(function () use ($f, $user) {
            $f->update(['status' => 'submitted', 'submitted_at' => now(), 'internal_approved_by' => $user->id, 'internal_approved_at' => now()]);
            $this->announceFundingSubmitted($f, $user);
        });
    }

    /** Ajukan ulang setelah Keuangan meminta revisi (nominal / alasan boleh diubah). */
    public function resubmitFunding(FundingRequest $f, float $amount, string $purpose, User $user): void
    {
        if (! in_array($f->status, ['revision'], true)) {
            throw new WorkflowException('Hanya pengajuan berstatus "diminta revisi" yang bisa diajukan ulang.');
        }

        DB::transaction(function () use ($f, $amount, $purpose, $user) {
            $f->update(['amount' => $amount, 'purpose' => $purpose, 'status' => 'submitted', 'submitted_at' => now(), 'decided_at' => null, 'decision_note' => null, 'decided_by_name' => null]);
            $this->announceFundingSubmitted($f, $user, true);
        });
    }

    private function announceFundingSubmitted(FundingRequest $f, User $user, bool $again = false): void
    {
        $pr = $f->request;
        $rp = number_format((float) $f->amount, 0, ',', '.');
        $this->event($pr, 'funding_submitted', ($again ? 'Pengajuan dana diajukan ulang' : 'Dana diajukan ke Keuangan')." ({$f->number}): Rp {$rp}", $user);
        $this->report('keuangan', 'out', 'funding_request', "Pengajuan dana {$f->number}: Rp {$rp}", "Untuk: {$pr->title} ({$pr->number})\nTujuan: {$f->purpose}", $pr, $f, true, $user);
        $this->report('inventaris', 'out', 'status_update', "Permintaan {$pr->number}: dana sedang diajukan ke Keuangan", "Pengadaan telah memilih vendor dan mengajukan dana Rp {$rp}. Menunggu keputusan Keuangan.", $pr, $f, true, $user);
        ActivityLogger::log('funding.submitted', "Pengajuan dana {$f->number} Rp {$rp} dikirim ke Keuangan", $f, $user->id);
        $this->webhooks->dispatch('funding.submitted', Presenter::funding($f->fresh('request')));
    }

    /**
     * Keputusan Keuangan. Dipanggil dari halaman Meja Keuangan (user internal) ATAU dari REST API
     * (sistem Keuangan) — dalam kasus API $user null dan $byName diisi nama pejabat/sistem.
     *
     * @param  'approve'|'reject'|'revision'  $decision
     */
    public function financeDecide(FundingRequest $f, string $decision, ?float $approvedAmount, ?string $note, string $byName, ?User $user = null, ?string $financeRef = null, ?string $actorLabel = null): void
    {
        if ($f->status !== 'submitted') {
            throw new WorkflowException('Pengajuan ini tidak sedang menunggu keputusan Keuangan.');
        }
        if (in_array($decision, ['reject', 'revision'], true) && blank($note)) {
            throw new WorkflowException('Isi catatan/alasan keputusan.');
        }

        DB::transaction(function () use ($f, $decision, $approvedAmount, $note, $byName, $user, $financeRef, $actorLabel) {
            $pr = $f->request;
            $common = ['decided_by_name' => $byName, 'decided_by' => $user?->id, 'decided_at' => now(), 'decision_note' => $note, 'finance_ref' => $financeRef ?: $f->finance_ref];

            if ($decision === 'approve') {
                $amt = $approvedAmount ?? (float) $f->amount;
                if ($amt <= 0) {
                    throw new WorkflowException('Nominal yang disetujui harus lebih dari nol.');
                }
                $f->update($common + ['status' => 'approved', 'approved_amount' => $amt]);
                $msg = 'Keuangan MENYETUJUI pengajuan dana '.$f->number.' sebesar Rp '.number_format($amt, 0, ',', '.').($amt < $f->amount ? ' (dipotong dari Rp '.number_format((float) $f->amount, 0, ',', '.').')' : '');
                $subject = "Dana {$f->number} disetujui";
            } elseif ($decision === 'reject') {
                $f->update($common + ['status' => 'rejected', 'approved_amount' => null]);
                $pr->update(['status' => 'sourcing']);
                $msg = "Keuangan MENOLAK pengajuan dana {$f->number}";
                $subject = "Dana {$f->number} ditolak";
            } else {
                $f->update($common + ['status' => 'revision']);
                $msg = "Keuangan meminta REVISI pengajuan dana {$f->number}";
                $subject = "Dana {$f->number}: diminta revisi";
            }

            $this->event($pr, 'funding_'.$decision, $msg.($note ? " — {$note}" : '').". Oleh {$byName}", $user, $actorLabel);
            $this->report('keuangan', 'in', 'funding_decision', $subject, "Oleh: {$byName}".($financeRef ? "\nNo. dokumen Keuangan: {$financeRef}" : '').($note ? "\nCatatan: {$note}" : ''), $pr, $f, true, $user);
            ActivityLogger::log('funding.'.$decision, $msg, $f, $user?->id);
        });

        $this->webhooks->dispatch('request.status_changed', Presenter::request($f->request->fresh(), false));
    }

    /** Dana dicairkan Keuangan ke Pengadaan. */
    public function disburse(FundingRequest $f, float $amount, string $method, ?string $reference, string $byName, ?User $user = null, ?string $actorLabel = null): void
    {
        if ($f->status !== 'approved') {
            throw new WorkflowException('Dana hanya bisa dicairkan untuk pengajuan yang sudah disetujui Keuangan.');
        }
        if ($amount <= 0 || $amount > (float) $f->approved_amount + 0.005) {
            throw new WorkflowException('Nominal pencairan tidak boleh melebihi nominal yang disetujui (Rp '.number_format((float) $f->approved_amount, 0, ',', '.').').');
        }

        DB::transaction(function () use ($f, $amount, $method, $reference, $byName, $user, $actorLabel) {
            $pr = $f->request;
            $f->update(['status' => 'disbursed', 'disbursed_amount' => $amount, 'disbursed_at' => now(), 'disbursement_method' => $method, 'disbursement_ref' => $reference]);
            $pr->update(['status' => 'funded']);

            $rp = number_format($amount, 0, ',', '.');
            $this->event($pr, 'disbursed', "Dana Rp {$rp} dicairkan Keuangan ({$method}".($reference ? ", ref {$reference}" : '').'). Pengadaan boleh membuat PO.', $user, $actorLabel);
            $this->report('keuangan', 'in', 'disbursement', "Dana {$f->number} dicairkan: Rp {$rp}", "Metode: {$method}".($reference ? "\nReferensi: {$reference}" : '')."\nDicairkan oleh: {$byName}", $pr, $f, true, $user);
            $this->report('inventaris', 'out', 'status_update', "Permintaan {$pr->number}: dana sudah cair", 'Dana telah dicairkan. Pengadaan segera memesan ke vendor.', $pr, $f, true, $user);
            ActivityLogger::log('funding.disbursed', "Dana {$f->number} dicairkan Rp {$rp}", $f, $user?->id);
        });

        $this->statusChanged($f->request);
    }

    // =====================================================================
    //  4. PURCHASE ORDER
    // =====================================================================

    public function createPurchaseOrder(PurchaseRequest $pr, array $data, User $user): PurchaseOrder
    {
        $funding = $pr->fundingRequests()->where('status', 'disbursed')->latest('id')->first();
        if (! $funding) {
            throw new WorkflowException('PO baru bisa dibuat setelah dana dicairkan oleh Keuangan.');
        }
        if ($pr->purchaseOrders()->where('status', '!=', 'cancelled')->exists()) {
            throw new WorkflowException('Permintaan ini sudah punya PO yang aktif.');
        }
        $quotation = $pr->quotations()->where('is_selected', true)->with('items')->first();
        if (! $quotation) {
            throw new WorkflowException('Tidak ada penawaran terpilih.');
        }

        return DB::transaction(function () use ($pr, $data, $user, $funding, $quotation) {
            $subtotal = 0;
            $lines = [];
            foreach ($quotation->items as $qi) {
                $item = $pr->items->firstWhere('id', $qi->purchase_request_item_id);
                $lines[] = [
                    'purchase_request_item_id' => $item->id,
                    'description' => $item->name.($item->specification ? " — {$item->specification}" : ''),
                    'quantity' => $qi->quantity,
                    'unit' => $item->unit,
                    'unit_price' => $qi->unit_price,
                ];
                $subtotal += $qi->quantity * $qi->unit_price;
            }

            $taxPct = (float) ($data['tax_percent'] ?? config('procura.default_tax_percent'));
            $tax = round($subtotal * $taxPct / 100, 2);
            $total = $subtotal + $tax;

            if ($total > (float) $funding->disbursed_amount + 0.005) {
                throw new WorkflowException('Total PO (Rp '.number_format($total, 0, ',', '.').') melebihi dana yang dicairkan (Rp '.number_format((float) $funding->disbursed_amount, 0, ',', '.').'). Ajukan tambahan dana atau kurangi PPN/item.');
            }

            $po = PurchaseOrder::create([
                'number' => DocNumber::next('PO'),
                'purchase_request_id' => $pr->id,
                'funding_request_id' => $funding->id,
                'quotation_id' => $quotation->id,
                'vendor_id' => $quotation->vendor_id,
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'subtotal' => $subtotal,
                'tax_percent' => $taxPct,
                'tax_amount' => $tax,
                'total' => $total,
                'payment_terms' => $data['payment_terms'] ?? $quotation->payment_terms,
                'delivery_address' => $data['delivery_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
            ]);
            foreach ($lines as $l) {
                $po->items()->create($l);
            }
            $this->event($pr, 'po_created', "PO {$po->number} dibuat untuk {$quotation->vendor->name}: Rp ".number_format($total, 0, ',', '.'), $user);

            return $po;
        });
    }

    /** @return array{0: bool, 1: ?string} hasil email ke vendor */
    public function sendPurchaseOrder(PurchaseOrder $po, User $user): array
    {
        if ($po->status !== 'draft') {
            throw new WorkflowException('Hanya PO berstatus draf yang bisa dikirim.');
        }

        DB::transaction(function () use ($po, $user) {
            $po->update(['status' => 'sent', 'sent_at' => now()]);
            $po->request->update(['status' => 'ordered']);
            $this->event($po->request, 'po_sent', "PO {$po->number} dikirim ke {$po->vendor->name}", $user);
            $this->report('inventaris', 'out', 'status_update', "Permintaan {$po->request->number}: barang sudah dipesan", "PO {$po->number} dikirim ke {$po->vendor->name}".($po->expected_date ? ', perkiraan tiba '.$po->expected_date->format('d/m/Y') : '').'.', $po->request, $po->funding, true, $user);
            ActivityLogger::log('po.sent', "PO {$po->number} dikirim ke {$po->vendor->name}", $po, $user->id);
        });

        $mail = $this->vendorMailer->sendPurchaseOrder($po);
        $this->webhooks->dispatch('purchase_order.sent', Presenter::order($po));
        $this->statusChanged($po->request);

        return $mail;
    }

    public function cancelPurchaseOrder(PurchaseOrder $po, User $user): void
    {
        if (! in_array($po->status, ['draft', 'sent'], true) || $po->receipts()->exists()) {
            throw new WorkflowException('PO yang sudah menerima barang tidak bisa dibatalkan.');
        }

        DB::transaction(function () use ($po, $user) {
            $po->update(['status' => 'cancelled']);
            $po->request->update(['status' => 'funded']);
            $this->event($po->request, 'po_cancelled', "PO {$po->number} dibatalkan", $user);
        });
    }

    // =====================================================================
    //  5. PENERIMAAN BARANG / JASA
    // =====================================================================

    /** @param array{received_date:string,delivery_note_no?:?string,condition?:string,vendor_score?:?int,notes?:?string,lines:array<int,array{po_item_id:int,quantity_received:float,quantity_rejected?:float,note?:?string}>} $data */
    public function receiveGoods(PurchaseOrder $po, array $data, User $user): GoodsReceipt
    {
        if (! in_array($po->status, ['sent', 'partial'], true)) {
            throw new WorkflowException('PO harus sudah dikirim ke vendor sebelum mencatat penerimaan.');
        }

        return DB::transaction(function () use ($po, $data, $user) {
            $po->load('items');
            $gr = GoodsReceipt::create([
                'number' => DocNumber::next('GR'),
                'purchase_order_id' => $po->id,
                'received_date' => $data['received_date'],
                'delivery_note_no' => $data['delivery_note_no'] ?? null,
                'condition' => $data['condition'] ?? 'good',
                'vendor_score' => $data['vendor_score'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $user->id,
            ]);

            $any = false;
            foreach ($data['lines'] as $line) {
                $received = (float) $line['quantity_received'];
                $rejected = (float) ($line['quantity_rejected'] ?? 0);
                if ($received <= 0 && $rejected <= 0) {
                    continue;
                }
                $item = $po->items->firstWhere('id', $line['po_item_id']);
                if (! $item) {
                    throw new WorkflowException('Baris penerimaan tidak sesuai dengan PO.');
                }
                if ($received > $item->outstanding() + 0.0001) {
                    throw new WorkflowException("Jumlah diterima untuk \"{$item->description}\" melebihi sisa pesanan (".rtrim(rtrim(number_format($item->outstanding(), 2, '.', ''), '0'), '.').').');
                }
                $gr->items()->create(['purchase_order_item_id' => $item->id, 'quantity_received' => $received, 'quantity_rejected' => $rejected, 'note' => $line['note'] ?? null]);
                $item->increment('received_quantity', $received);
                $any = true;
            }
            if (! $any) {
                throw new WorkflowException('Isi jumlah yang diterima minimal pada satu baris.');
            }

            $po->load('items');
            $complete = $po->items->every(fn ($i) => $i->outstanding() <= 0.0001);
            $po->update(['status' => $complete ? 'received' : 'partial']);

            if ($complete) {
                $po->request->update(['status' => 'received']);
            }
            if (! empty($data['vendor_score'])) {
                $po->vendor->addScore((int) $data['vendor_score']);
            }

            $pr = $po->request;
            $this->event($pr, 'received', $complete ? "Semua barang/jasa PO {$po->number} diterima ({$gr->number})" : "Penerimaan sebagian PO {$po->number} ({$gr->number})", $user);
            $this->report('inventaris', 'out', 'status_update', $complete ? "Permintaan {$pr->number}: barang/jasa sudah diterima Pengadaan" : "Permintaan {$pr->number}: penerimaan sebagian", 'Kondisi: '.['good' => 'baik', 'partial_damage' => 'sebagian rusak', 'rejected' => 'ditolak'][$gr->condition]."\nSegera diserahkan ke Inventaris setelah pemeriksaan akhir.", $pr, $po->funding, true, $user);
            ActivityLogger::log('goods.received', "Penerimaan {$gr->number} untuk PO {$po->number}", $gr, $user->id);
            $this->webhooks->dispatch('goods.received', ['receipt' => $gr->number, 'purchase_order' => Presenter::order($po), 'complete' => $complete]);

            return $gr;
        });
    }

    // =====================================================================
    //  6. PEMBAYARAN VENDOR & LPJ
    // =====================================================================

    public function payVendor(PurchaseOrder $po, float $amount, string $paidAt, string $method, ?string $reference, ?string $notes, User $user): VendorPayment
    {
        if (! in_array($po->status, ['sent', 'partial', 'received'], true)) {
            throw new WorkflowException('Pembayaran hanya untuk PO yang sudah dikirim ke vendor.');
        }
        $funding = $po->funding;
        if (! $funding || $funding->status !== 'disbursed') {
            throw new WorkflowException('Dana untuk PO ini belum cair atau LPJ-nya sudah ditutup.');
        }
        if ($amount <= 0) {
            throw new WorkflowException('Nominal pembayaran harus lebih dari nol.');
        }
        $owed = $po->total - $po->paidTotal();
        if ($amount > $owed + 0.005) {
            throw new WorkflowException('Pembayaran melebihi sisa tagihan PO (Rp '.number_format($owed, 0, ',', '.').').');
        }
        if ($amount > $funding->remaining() + 0.005) {
            throw new WorkflowException('Dana yang tersedia tidak cukup (sisa Rp '.number_format($funding->remaining(), 0, ',', '.').').');
        }

        return DB::transaction(function () use ($po, $funding, $amount, $paidAt, $method, $reference, $notes, $user) {
            $p = VendorPayment::create([
                'purchase_order_id' => $po->id, 'funding_request_id' => $funding->id, 'amount' => $amount, 'paid_at' => $paidAt,
                'method' => $method, 'reference' => $reference, 'notes' => $notes, 'paid_by' => $user->id,
            ]);
            $this->event($po->request, 'vendor_paid', 'Pembayaran ke '.$po->vendor->name.' Rp '.number_format($amount, 0, ',', '.').($reference ? " (ref {$reference})" : ''), $user);
            ActivityLogger::log('vendor.paid', "Bayar vendor untuk {$po->number}: Rp ".number_format($amount, 0, ',', '.'), $po, $user->id);

            return $p;
        });
    }

    /** Pertanggungjawaban dana (LPJ) ke Keuangan: dana terpakai dihitung dari pembayaran vendor, sisa dikembalikan. */
    public function settleFunding(FundingRequest $f, ?string $note, User $user): void
    {
        if ($f->status !== 'disbursed') {
            throw new WorkflowException('LPJ hanya untuk dana yang sudah dicairkan.');
        }
        $spent = (float) $f->payments()->sum('amount');
        $po = $f->request->purchaseOrders()->where('status', '!=', 'cancelled')->first();
        if ($spent <= 0 && $po) {
            throw new WorkflowException('Belum ada pembayaran vendor yang dicatat, LPJ belum bisa dibuat.');
        }
        if ($po && $spent + 0.005 < $po->total) {
            throw new WorkflowException('Tagihan PO belum lunas. Lunasi dulu sebelum LPJ, atau catat pembayaran yang kurang.');
        }

        DB::transaction(function () use ($f, $note, $user, $spent) {
            $returned = max(0, (float) $f->disbursed_amount - $spent);
            $f->update(['status' => 'settled', 'actual_spent' => $spent, 'returned_amount' => $returned, 'settled_at' => now(), 'settlement_note' => $note]);

            $pr = $f->request;
            $rpS = number_format($spent, 0, ',', '.');
            $rpR = number_format($returned, 0, ',', '.');
            $this->event($pr, 'settled', "LPJ dana {$f->number}: terpakai Rp {$rpS}, sisa dikembalikan Rp {$rpR}", $user);
            $this->report('keuangan', 'out', 'settlement', "LPJ dana {$f->number}", "Dana cair: Rp ".number_format((float) $f->disbursed_amount, 0, ',', '.')."\nTerpakai: Rp {$rpS}\nSisa dikembalikan: Rp {$rpR}".($note ? "\nCatatan: {$note}" : ''), $pr, $f, true, $user);
            ActivityLogger::log('funding.settled', "LPJ {$f->number}: terpakai Rp {$rpS}", $f, $user->id);
            $this->completeIfDone($pr);
        });

        $this->webhooks->dispatch('funding.settled', Presenter::funding($f->fresh('request')));
    }

    // =====================================================================
    //  7. SERAH TERIMA -> INVENTARIS
    // =====================================================================

    public function handOver(PurchaseRequest $pr, string $date, string $receiver, ?string $notes, User $user): Handover
    {
        $this->assertStatus($pr, ['received'], 'Serah terima hanya bisa dilakukan setelah semua barang/jasa diterima.');
        $po = $pr->purchaseOrders()->where('status', 'received')->latest('id')->first();

        return DB::transaction(function () use ($pr, $po, $date, $receiver, $notes, $user) {
            $h = Handover::create([
                'number' => DocNumber::next('HO'),
                'purchase_request_id' => $pr->id,
                'purchase_order_id' => $po?->id,
                'handover_date' => $date,
                'receiver_name' => $receiver,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);
            $pr->update(['status' => 'handed_over']);
            $this->event($pr, 'handed_over', "Diserahkan ke Inventaris ({$h->number}), diterima oleh {$receiver}", $user);
            $this->report('inventaris', 'out', 'handover', "Serah terima {$h->number}: {$pr->title}", $this->poSummary($po)."\nDiterima oleh: {$receiver}".($notes ? "\nCatatan: {$notes}" : '')."\n\nMohon konfirmasi penerimaan dan catat sebagai aset/persediaan.", $pr, null, true, $user);
            ActivityLogger::log('handover.created', "Serah terima {$h->number} untuk {$pr->number}", $h, $user->id);
            $this->completeIfDone($pr);
            $this->webhooks->dispatch('handover.created', Presenter::handover($h) + ['request' => Presenter::request($pr->fresh(), false)]);

            return $h;
        });
    }

    /** Inventaris menandai serah terima sudah diterima & dicatat di sistem mereka. */
    public function confirmHandover(Handover $h, string $byName, ?User $user = null, ?string $actorLabel = null): void
    {
        if ($h->status === 'confirmed') {
            return;
        }

        DB::transaction(function () use ($h, $byName, $user, $actorLabel) {
            $h->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmed_by_name' => $byName]);
            $pr = $h->request;
            $this->event($pr, 'handover_confirmed', "Inventaris ({$byName}) mengonfirmasi serah terima {$h->number}", $user, $actorLabel);
            $this->report('inventaris', 'in', 'handover', "Konfirmasi serah terima {$h->number}", "Dikonfirmasi oleh {$byName}. Barang sudah masuk pencatatan Inventaris.", $pr, null, true, $user);
        });
    }

    // =====================================================================
    //  Pembantu
    // =====================================================================

    private function completeIfDone(PurchaseRequest $pr): void
    {
        $pr->refresh();
        if ($pr->status !== 'handed_over') {
            return;
        }
        $open = $pr->fundingRequests()->whereIn('status', ['pending_internal', 'submitted', 'approved', 'revision', 'disbursed'])->exists();
        if (! $open) {
            $pr->update(['status' => 'completed', 'completed_at' => now()]);
            $this->event($pr, 'completed', 'Permintaan selesai: barang diserahkan dan dana sudah dipertanggungjawabkan', null, 'Sistem');
        }
    }

    public function assertStatus(PurchaseRequest $pr, array $allowed, string $message): void
    {
        if (! in_array($pr->status, $allowed, true)) {
            throw new WorkflowException($message.' (status sekarang: '.Workflow::label(Workflow::REQUEST_STATUS, $pr->status).')');
        }
    }

    public function event(PurchaseRequest $pr, string $type, string $description, ?User $user = null, ?string $actorLabel = null): void
    {
        RequestEvent::create([
            'purchase_request_id' => $pr->id, 'type' => $type, 'description' => $description,
            'user_id' => $user?->id, 'actor_label' => $actorLabel, 'created_at' => now(),
        ]);
    }

    public function report(string $counterpart, string $direction, string $kind, string $subject, ?string $body, ?PurchaseRequest $pr, ?FundingRequest $f, bool $auto, ?User $user = null): Report
    {
        return Report::create([
            'number' => DocNumber::next('RP'),
            'counterpart' => $counterpart, 'direction' => $direction, 'kind' => $kind, 'subject' => $subject, 'body' => $body,
            'purchase_request_id' => $pr?->id, 'funding_request_id' => $f?->id, 'is_auto' => $auto, 'created_by' => $user?->id,
        ]);
    }

    private function itemsSummary(PurchaseRequest $pr): string
    {
        return $pr->items->map(fn ($i) => '• '.$i->name.' — '.rtrim(rtrim(number_format($i->quantity, 2, '.', ''), '0'), '.').' '.$i->unit)->implode("\n");
    }

    private function poSummary(?PurchaseOrder $po): string
    {
        if (! $po) {
            return '';
        }
        $po->loadMissing('items');

        return $po->items->map(fn ($i) => '• '.$i->description.' — '.rtrim(rtrim(number_format($i->received_quantity, 2, '.', ''), '0'), '.').' '.$i->unit)->implode("\n");
    }

    private function statusChanged(PurchaseRequest $pr): void
    {
        $this->webhooks->dispatch('request.status_changed', Presenter::request($pr->fresh(), false));
    }

    private function syncStatusOnly(PurchaseRequest $pr): void
    {
        // titik kait untuk perluasan (mis. notifikasi); sengaja kosong
    }
}
