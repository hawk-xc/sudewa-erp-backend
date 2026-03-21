<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionItemSales extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_item_sales';

    protected $fillable = [
        'uuid',
        'unit_transaction_item_id',
        'unit_transaction_item_detail_id',
    ];

    protected $casts = [
        'unit_transaction_item_id' => 'integer',
        'unit_transaction_item_detail_id' => 'integer',
    ];

    public function unitTransactionItem()
    {
        return $this->belongsTo(UnitTransactionItem::class);
    }

    public function unitTransactionItemDetail()
    {
        return $this->belongsTo(UnitTransactionItemDetail::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
