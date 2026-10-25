<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogItem extends Model
{
    protected $fillable = ['sku', 'name', 'category', 'unit', 'reference_price', 'description', 'is_active'];

    protected $casts = ['reference_price' => 'float', 'is_active' => 'boolean'];
}
