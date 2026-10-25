<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasCleanDateSerialization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use HasAttachments, HasCleanDateSerialization;

    protected $fillable = [
        'number', 'source', 'external_ref', 'requester_name', 'requester_unit', 'category', 'title', 'justification',
        'priority', 'needed_date', 'status', 'estimated_total', 'created_by', 'handled_by', 'rejection_reason', 'completed_at',
    ];

    protected $casts = ['needed_date' => 'date', 'estimated_total' => 'float', 'completed_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function fundingRequests(): HasMany
    {
        return $this->hasMany(FundingRequest::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function handovers(): HasMany
    {
        return $this->hasMany(Handover::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'rejected', 'cancelled'], true);
    }

    /** Posisi di jalur pengadaan (indeks stasiun 0..6) untuk RouteTracker. */
    public function stationKey(): string
    {
        return match ($this->status) {
            'submitted' => 'submitted',
            'reviewing' => 'reviewing',
            'sourcing' => 'sourcing',
            'funding', 'funded' => 'funding',
            'ordered' => 'ordered',
            'received' => 'received',
            'handed_over', 'completed' => 'handed_over',
            default => 'submitted',
        };
    }

    public function activeFunding(): ?FundingRequest
    {
        return $this->fundingRequests()->whereNotIn('status', ['rejected', 'cancelled'])->latest('id')->first();
    }
}
