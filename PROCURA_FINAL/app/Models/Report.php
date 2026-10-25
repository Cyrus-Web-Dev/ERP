<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = [
        'number', 'counterpart', 'direction', 'kind', 'subject', 'body', 'purchase_request_id', 'funding_request_id',
        'is_auto', 'read_at', 'created_by',
    ];

    protected $casts = ['is_auto' => 'boolean', 'read_at' => 'datetime'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function funding(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class, 'funding_request_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
