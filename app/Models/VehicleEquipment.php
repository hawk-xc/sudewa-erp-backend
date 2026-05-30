<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleEquipment extends Model
{
    use HasFactory;

    protected $table = 'vehicle_equipments';
    
    protected $fillable = [
        'uuid',
        'code',
        'name'
    ];


    public function goodsTransactionDetails()
    {
        return $this->hasMany(GoodsTransactionDetail::class, 'vehicle_equipment_id', 'id');
    }

    public function getAvailableStock(?int $warehouseId = null, $excludeDetailId = null)
    {
        $purchasedQuery = $this->goodsTransactionDetails()
            ->whereHas('goodsTransaction', function ($q) {
                $q->where('type', 'receipt');
            });

        $soldQuery = $this->goodsTransactionDetails()
            ->whereHas('goodsTransaction', function ($q) {
                $q->where('type', 'issue');
            });

        if ($warehouseId) {
            $purchasedQuery->whereHas('goodsTransaction.company.warehouse', function ($q) use ($warehouseId) {
                $q->where('id', $warehouseId);
            });
            $soldQuery->whereHas('goodsTransaction.company.warehouse', function ($q) use ($warehouseId) {
                $q->where('id', $warehouseId);
            });
        }

        if ($excludeDetailId) {
            $soldQuery->where('id', '!=', $excludeDetailId);
        }

        $purchased = $purchasedQuery->sum('qty');
        $sold = $soldQuery->sum('qty');

        return $purchased - $sold;
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
