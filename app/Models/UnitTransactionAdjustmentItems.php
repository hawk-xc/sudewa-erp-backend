<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionAdjustmentItems extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_adjustment_items';

    protected $fillable = [
        'uuid',
        'unit_transaction_adjustment_id',
        'unit_transaction_item_id',
        'unit_transaction_item_detail_id',
        'qty',
    ];

    protected $casts = [
        'unit_transaction_adjustment_id' => 'integer',
        'unit_transaction_item_id' => 'integer',
        'unit_transaction_item_detail_id' => 'integer',
        'qty' => 'integer',
    ];

    public function unitTransactionAdjustment()
    {
        return $this->belongsTo(UnitTransactionAdjustment::class);
    }

    public function unitTransactionItem()
    {
        return $this->belongsTo(UnitTransactionItem::class);
    }

    public function unitTransactionItemDetail()
    {
        return $this->belongsTo(UnitTransactionItemDetail::class);
    }

    public function unitType()
    {
        return $this->unitTransactionItem->unitType();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
