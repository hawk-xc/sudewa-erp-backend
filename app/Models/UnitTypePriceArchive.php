<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitTypePriceArchive extends Model
{
    use HasFactory;

    protected $table = 'unit_type_price_archives';
    
    protected $fillable = [
        'uuid',
        'unit_type_id',
        'user_id',
        'buy_price',
        'sell_price',
    ];

    protected $casts = [
        'unit_type_id' => 'integer',
        'user_id' => 'integer',
        'buy_price' => 'integer',
        'sell_price' => 'integer'
    ];

    public function 
}
