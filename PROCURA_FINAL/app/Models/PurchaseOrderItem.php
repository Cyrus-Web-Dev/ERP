<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'purchase_request_item_id', 'description', 'quantity', 'received_quantity', 'unit', 'unit_price'];

    protected $casts = ['quantity' => 'float', 'received_quantity' => 'float', 'unit_price' => 'float'];

    public function outstanding(): float
    {
        return max(0, $this->quantity - $this->received_quantity);
    }
}
