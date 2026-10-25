<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_request_id', 'type', 'description', 'user_id', 'actor_label', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, "purchase_request_id");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
