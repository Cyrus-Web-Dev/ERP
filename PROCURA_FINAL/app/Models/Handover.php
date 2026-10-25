<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Handover extends Model
{
    use HasAttachments, HasCleanDateSerialization;

    protected $fillable = ['number', 'purchase_request_id', 'purchase_order_id', 'handover_date', 'receiver_name', 'notes', 'status', 'confirmed_at', 'confirmed_by_name', 'created_by'];

    protected $casts = ['handover_date' => 'date', 'confirmed_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
