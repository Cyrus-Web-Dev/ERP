<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = ['purchase_request_id', 'catalog_item_id', 'name', 'specification', 'quantity', 'unit', 'estimated_price'];

    protected $casts = ['quantity' => 'float', 'estimated_price' => 'float'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
