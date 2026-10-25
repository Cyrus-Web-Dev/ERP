<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasAttachments, HasCleanDateSerialization;

    protected $fillable = [
        'number', 'purchase_request_id', 'funding_request_id', 'quotation_id', 'vendor_id', 'order_date', 'expected_date',
        'subtotal', 'tax_percent', 'tax_amount', 'total', 'payment_terms', 'delivery_address', 'notes', 'status', 'sent_at', 'created_by',
    ];

    protected $casts = [
        'order_date' => 'date', 'expected_date' => 'date', 'subtotal' => 'float', 'tax_percent' => 'float',
        'tax_amount' => 'float', 'total' => 'float', 'sent_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function funding(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class, 'funding_request_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->sum('amount');
    }
}
