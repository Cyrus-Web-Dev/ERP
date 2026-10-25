<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = ['api_client_id', 'event', 'payload', 'status', 'response_code', 'attempts', 'error', 'delivered_at'];

    protected $casts = ['payload' => 'array', 'delivered_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class, 'api_client_id');
    }
}
