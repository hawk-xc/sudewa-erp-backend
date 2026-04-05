<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitTypePriceArchive extends Model
{
    use HasFactory;
<<<<<<< HEAD

    protected $table = 'unit_type_price_archives';

    protected $fillable = [
        'uuid',
        'unit_type_id',
        'user_id',
        'buy_price',
        'sell_price',
        'note',
    ];

    protected $casts = [
        'unit_type_id' => 'integer',
        'user_id' => 'integer',
        'buy_price' => 'integer',
        'sell_price' => 'integer',
    ];

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
=======
>>>>>>> 3046f9a (fix: resolve conflict)
}
