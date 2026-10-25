<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasAttachments, HasCleanDateSerialization;

    protected $fillable = [
        'number', 'purchase_request_id', 'vendor_id', 'vendor_ref', 'quoted_date', 'valid_until', 'lead_time_days',
        'payment_terms', 'total', 'notes', 'is_selected', 'selection_reason', 'created_by',
    ];

    protected $casts = ['quoted_date' => 'date', 'valid_until' => 'date', 'total' => 'float', 'is_selected' => 'boolean'];

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
