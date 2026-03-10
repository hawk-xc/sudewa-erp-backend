<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionItemDetail extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_item_details';

    protected $fillable = [
        'uuid',
        'unit_transaction_item_id',
        'color',
        'machine_number',
        'chassis_number',
        'in_stock',
    ];

    protected $casts = [
        'in_stock' => 'bool',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function unitTransactionItem()
    {
        return $this->belongsTo(UnitTransactionItem::class);
    }

    public function warehouseMovement()
    {
        return $this->hasOne(WarehouseMovement::class, 'unit_transaction_item_detail_id', 'id');
    }

    public function receiptStock()
    {
        $unitTransaction = $this->unitTransactionItem->unitTransaction;
        $warehouse = $unitTransaction->warehouse;

        return WarehouseMovement::firstOrCreate(
            [
                'unit_transaction_item_detail_id' => $this->id,
                'status' => 'in',
            ],
            [
                'warehouse_id' => $warehouse->id,
                'unit_transaction_id' => $unitTransaction->id,
                'status' => 'in',
            ]
        );
    }

    public function dispatchStock()
    {
        $movement = $this->warehouseMovement()->first();

        if (! $movement) {
            throw new \Exception('Vehicle not found in warehouse');
        }

        $movement->update([
            'status' => 'out',
        ]);

        UnitTransactionItemDetail::findOrFail($movement->unitTransactionItemDetail->id)->update([
            'in_stock' => false,
        ]);

        return $movement;
    }
}
