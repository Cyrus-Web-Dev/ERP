<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwofaCode extends Model
{
    const UPDATED_AT = null;

    protected $table = 'twofa_codes';

    protected $fillable = ['user_id', 'code_hash', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
