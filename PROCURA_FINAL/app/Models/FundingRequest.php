<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingRequest extends Model
{
    use HasAttachments;

    protected $fillable = [
        'number', 'purchase_request_id', 'quotation_id', 'amount', 'purpose', 'status', 'submitted_by', 'submitted_at',
        'internal_approved_by', 'internal_approved_at', 'decided_by_name', 'decided_by', 'decided_at', 'approved_amount',
        'decision_note', 'finance_ref', 'disbursed_amount', 'disbursed_at', 'disbursement_ref', 'disbursement_method',
        'actual_spent', 'returned_amount', 'settled_at', 'settlement_note',
    ];

    protected $casts = [
        'amount' => 'float', 'approved_amount' => 'float', 'disbursed_amount' => 'float', 'actual_spent' => 'float', 'returned_amount' => 'float',
        'submitted_at' => 'datetime', 'internal_approved_at' => 'datetime', 'decided_at' => 'datetime', 'disbursed_at' => 'datetime', 'settled_at' => 'datetime',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }

    /** Sisa dana yang belum dipakai membayar vendor. */
    public function remaining(): float
    {
        return max(0, (float) ($this->disbursed_amount ?? 0) - (float) $this->payments()->sum('amount'));
    }
}
