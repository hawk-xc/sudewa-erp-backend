<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialTransactionDetail extends Model
{
    use HasFactory;

    protected $table = 'material_transaction_details';

    protected $fillable = [
        'uuid',
        'material_transaction_id',
        'material_id',
        'in_stock', // bool -> default false
        'is_forecast', // bool -> default true 
        'qty',
        'price',
        'description',
    ];

    protected $appends = [
        'total',
    ];

    public function getTotalAttribute()
    {
        return $this->price * $this->qty;
    }

    public function materialTransaction()
    {
        return $this->belongsTo(MaterialTransaction::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
