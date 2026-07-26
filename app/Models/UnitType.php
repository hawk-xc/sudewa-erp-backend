<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    ];

    protected $casts = [
        'brand_id' => 'integer',
        'netto_weight' => 'integer',
        'bruto_weight' => 'integer',
    ];

    protected $appends = [
        'buy_price',
        'sell_price',
    ];

    protected $cachedDefaultPrice = null;
    protected bool $isDefaultPriceCached = false;

    public function getBuyPriceAttribute()
    {
        if (!$this->isDefaultPriceCached) {
            $this->cachedDefaultPrice = $this->relationLoaded('unitTypePriceVersions')
                ? ($this->unitTypePriceVersions->firstWhere('is_default', true) ?: $this->unitTypePriceVersions->first())
                : ($this->getDefaultPrice() ?: $this->getLatestPrice());
            $this->isDefaultPriceCached = true;
        }
        return $this->cachedDefaultPrice ? (int) $this->cachedDefaultPrice->buy_price : 0;
    }

    public function getSellPriceAttribute()
    {
        if (!$this->isDefaultPriceCached) {
            $this->cachedDefaultPrice = $this->relationLoaded('unitTypePriceVersions')
                ? ($this->unitTypePriceVersions->firstWhere('is_default', true) ?: $this->unitTypePriceVersions->first())
                : ($this->getDefaultPrice() ?: $this->getLatestPrice());
            $this->isDefaultPriceCached = true;
        }
        return $this->cachedDefaultPrice ? (int) $this->cachedDefaultPrice->sell_price : 0;
    }

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

    public function unitTypePriceVersions()
    {
        return $this->hasMany(UnitTypePriceVersion::class, 'unit_type_id', 'id');
    }

    public function getLatestPrice()
    {
        try {
            return $this->unitTypePriceVersions()->latest()->first();
        } catch (Exception $err) {
            Log::error('Error getting latest unit type price version: ' . $err->getMessage());
            return null;
        }
    }

    public function getDefaultPrice()
    {
        try {
            return $this->unitTypePriceVersions()
                ->where('is_default', true)
                ->firstOrFail();
        } catch (Exception $err) {
            Log::error('Error getting default unit type price version: ' . $err->getMessage());
            return null;
        }
    }
}
