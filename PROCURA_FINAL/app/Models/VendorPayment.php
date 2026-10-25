<?php

namespace App\Models;

use App\Models\Concerns\HasCleanDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    use HasCleanDateSerialization;

    protected $fillable = ['purchase_order_id', 'funding_request_id', 'amount', 'paid_at', 'method', 'reference', 'notes', 'paid_by'];

    protected $casts = ['amount' => 'float', 'paid_at' => 'date'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
