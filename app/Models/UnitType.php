<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitType extends Model
{
    use HasFactory;

    protected $table = 'unit_types';

    protected $fillable = [
        'uuid',
        'code',
        'brand_id',
        'name',
        'image',
        'unit_type',
        'unit_model',
        'netto_weight',
        'bruto_weight',
        'buy_price',
        'sell_price',
    ];

    protected $casts = [
        'brand_id' => 'integer',
        'netto_weight' => 'integer',
        'bruto_weight' => 'integer',
        'buy_price' => 'integer',
        'sell_price' => 'integer',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unitTransactionItems()
    {
        return $this->hasMany(UnitTransactionItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function getRealStock(int $warehouseId)
    {
        return UnitTransactionItemDetail::where('in_stock', true)
            ->whereHas('unitTransactionItem', function ($query) {
                $query->where('unit_type_id', $this->id);
            })
            ->whereHas('unitTransactionItem.unitTransaction', function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->count();
    }

    public function getForecastStock(int $warehouseId)
    {
        return UnitTransactionItemDetail::whereHas('unitTransactionItem', function ($query) {
            $query->where('unit_type_id', $this->id);
        })->whereHas('unitTransactionItem.unitTransaction', function ($query) use ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        })->count();
    }

    public function getUnitTypeItemDetails(?int $warehouseId = null)
    {
        return $this->unitTransactionItems()
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->whereHas('unitTransaction', function ($q2) use ($warehouseId) {
                    $q2->where('warehouse_id', $warehouseId);
                });
            })
            ->with(['unitTransactionItemDetails' => function ($q) {
                $q->where('in_stock', 1);
            }])
            ->get()
            ->pluck('unitTransactionItemDetails')
            ->flatten();
    }
}
