<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UnitTypePriceVersion extends Model
{
    use HasFactory;

    protected $table = 'unit_type_price_versions';

    protected $fillable = [
        'uuid',
        'unit_type_id',
        'name',
        'buy_price',
        'sell_price',
        'effective_from',
        'effective_until',
        'is_default',
        'is_lock',
    ];

    protected $casts = [
        'unit_type_id' => 'integer',
        'buy_price' => 'integer',
        'sell_price' => 'integer',
        'is_default' => 'boolean',
        'is_lock' => 'boolean',
    ];

    public function unitType()
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
