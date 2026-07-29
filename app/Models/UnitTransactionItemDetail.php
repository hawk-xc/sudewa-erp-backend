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
        'warehouse_sub_block_id',
        'color',
        'machine_number',
        'chassis_number',
        'in_stock',
        'is_forecast',
        'status',
        'stock_state',
    ];

    protected $casts = [
        'unit_transaction_item_id' => 'integer',
        'warehouse_sub_block_id' => 'integer',
        'in_stock' => 'bool',
        'is_forecast' => 'bool',
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

    public function unitTransactionAdjustmentItems()
    {
        return $this->hasMany(UnitTransactionAdjustmentItems::class);
    }

    public function unitTransactionRefunds()
    {
        return $this->belongsToMany(
            UnitTransactionRefund::class,
            'unit_transaction_refund_item_detail',
            'unit_transaction_item_detail_id',
            'unit_transaction_refund_id'
        );
    }

    public function warehouseSubBlock()
    {
        return $this->belongsTo(WarehouseSubBlock::class, 'warehouse_sub_block_id', 'id');
    }

    public function receiptStock(?int $activityId = null)
    {
        $unitTransaction = $this->unitTransactionItem->unitTransaction;

        return WarehouseMovement::firstOrCreate(
            [
                'unit_transaction_item_detail_id' => $this->id,
                'status' => 'in',
            ],
            [
                'warehouse_activity_id' => $activityId,
                'unit_transaction_id' => $unitTransaction->id,
                'status' => 'in',
            ]
        );
    }

    public function dispatchStock(?int $activityId = null)
    {
        $this->update([
            'is_forecast' => false,
            'in_stock' => false,
        ]);

        $unitTransaction = $this->unitTransactionItem->unitTransaction;

        return WarehouseMovement::firstOrCreate(
            [
                'unit_transaction_item_detail_id' => $this->id,
                'status' => 'out',
            ],
            [
                'warehouse_activity_id' => $activityId,
                'unit_transaction_id' => $unitTransaction->id,
                'status' => 'out',
            ]
        );
    }

    public function refundStock()
    {
        $this->update([
            'status' => 'refunded',
            'is_forecast' => false,
            'in_stock' => false,
        ]);

        $movement = $this->warehouseMovement()->where('status', 'in')->first();
        if ($movement) {
            $movement->update(['status' => 'out']);
        }

        $item = $this->unitTransactionItem;
        if ($item && $item->unitTransaction) {
            $item->unitTransaction->recalculateBillingTotals();
        }

        return $this;
    }

    public function returnStock()
    {
        $this->update([
            'status' => 'returned',
            'is_forecast' => false,
            'in_stock' => true,
        ]);

        $movement = $this->warehouseMovement()->where('status', 'in')->first();
        if ($movement) {
            $movement->update(['status' => 'out']);
        }

        $item = $this->unitTransactionItem;
        if ($item && $item->unitTransaction) {
            $item->unitTransaction->recalculateBillingTotals();
        }

        return $this;
    }

    public function assignWarehouseSubBlock(?int $warehouseSubBlockId = null)
    {
        $this->update([
            'warehouse_sub_block_id' => $warehouseSubBlockId,
        ]);

        return $this;
    }

    public function changeStatus(?string $stockState = null)
    {
        if (!in_array($stockState, ['draft', 'cancel', 'prepare','purchase_order','in_transit','receipt'])) {
            return false;
        }

        $this->update([
            'stock_state' => $stockState,
        ]);

        return true;
    }
}
