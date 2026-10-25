<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'code', 'name', 'category', 'contact_person', 'email', 'phone', 'address', 'npwp',
        'bank_name', 'bank_account', 'bank_holder', 'payment_terms_days', 'rating', 'rating_count', 'status', 'notes',
    ];

    protected $casts = ['rating' => 'float'];

    public function orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function isUsable(): bool
    {
        return $this->status === 'active';
    }

    /** Rata-rata berjalan skor kinerja setelah setiap penerimaan barang. */
    public function addScore(int $score): void
    {
        $count = $this->rating_count + 1;
        $this->update(['rating' => round((($this->rating * $this->rating_count) + $score) / $count, 2), 'rating_count' => $count]);
    }
}
