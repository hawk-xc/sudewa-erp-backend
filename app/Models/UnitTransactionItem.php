<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionItem extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_items';

    protected $fillable = [
        'uuid',
        'unit_transaction_id',
        'unit_type_id',
        'sparepart_id',
        'qty_total',
        'price',
        'bbn_price',
        'expedition_fee',
        'other_fee',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    public function unitTransactionItemDetails()
    {
        return $this->hasMany(UnitTransactionItemDetail::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
