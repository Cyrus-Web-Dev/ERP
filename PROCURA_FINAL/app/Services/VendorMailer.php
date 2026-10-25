<?php

namespace App\Services;

use App\Exceptions\GmailSendException;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Mengirim Purchase Order ke email vendor lewat Gmail API. Mengembalikan [berhasil, pesan_error]. */
class VendorMailer
{
    public function __construct(private GmailApiService $gmail)
    {
    }

    /** @return array{0: bool, 1: ?string} */
    public function sendPurchaseOrder(PurchaseOrder $po): array
    {
        $po->loadMissing(['vendor', 'items', 'request']);

        if (! $po->vendor->email) {
            return [false, 'Vendor belum punya alamat email. PO ditandai terkirim, tetapi email tidak dikirim.'];
        }

        try {
            $html = view('emails.purchase-order', ['po' => $po])->render();
            $text = "Halo {$po->vendor->name},\n\nTerlampir Purchase Order {$po->number} senilai Rp ".number_format($po->total, 0, ',', '.').". Mohon konfirmasi penerimaan dan jadwal pengiriman.";
            $this->gmail->send($po->vendor->email, $po->vendor->contact_person ?: $po->vendor->name, "Purchase Order {$po->number}", $html, $text);

            return [true, null];
        } catch (GmailSendException $e) {
            Log::warning("[VendorMailer] PO {$po->number} gagal dikirim: ".$e->getMessage());

            return [false, $e->userMessage()];
        } catch (Throwable $e) {
            Log::error('[VendorMailer] '.$e->getMessage());

            return [false, 'Terjadi kesalahan saat menyusun email PO.'];
        }
    }
}
