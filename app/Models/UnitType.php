<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnitType extends Model
{
    use HasFactory;

    protected $table = 'unit_types';

    protected $fillable = [
        'code',
        'brand_id',
        'name',
        'image',
        'type',
        'netto_weight',
        'bruto_weight',
    ];

    public function brand() 
    {
        return $this->belongsTo(Brand::class);
    }
}
