<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['goods_receipt_id', 'purchase_order_item_id', 'quantity_received', 'quantity_rejected', 'note'];

    protected $casts = ['quantity_received' => 'float', 'quantity_rejected' => 'float'];
}
