<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Material extends Model
{
    use HasFactory;

    protected $table = 'materials';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'price',
        'type',
    ];

    protected $casts = [
        'price' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function goodsTransactionDetails()
    {
        return $this->hasMany(GoodsTransactionDetail::class);
    }

    /**
     * Get real stock (finalized) for a specific warehouse.
     */
    public function getRealStock(int $warehouseId)
    {
        return $this->getAvailableStock($warehouseId);
    }

    /**
     * Get forecast stock (including pending) for a specific warehouse.
     */
    public function getForecastStock(int $warehouseId)
    {
        return $this->getAvailableStock($warehouseId);
    }

    public function getAvailableStock(?int $warehouseId = null, $excludeDetailId = null, ?int $companyId = null)
    {
        $purchasedQuery = $this->goodsTransactionDetails()
            ->whereHas('goodsTransaction', function ($q) use ($companyId) {
                $q->where('type', 'receipt');
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
            });

        $soldQuery = $this->goodsTransactionDetails()
            ->whereHas('goodsTransaction', function ($q) use ($companyId) {
                $q->where('type', 'issue');
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
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
}
