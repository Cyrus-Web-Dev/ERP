<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['quotation_id', 'purchase_request_item_id', 'unit_price', 'quantity', 'note'];

    protected $casts = ['unit_price' => 'float', 'quantity' => 'float'];
}
