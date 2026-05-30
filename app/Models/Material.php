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
        $purchased = $this->goodsTransactionDetails()
            ->whereHas('goodsTransaction', function ($q) use ($warehouseId) {
                $q->where('type', 'purchase')->where('warehouse_id', $warehouseId);
            })
            ->sum('qty');

        $sold = $this->goodsTransactionDetails()
            ->whereHas('goodsTransaction', function ($q) use ($warehouseId) {
                $q->where('type', 'sales')->where('warehouse_id', $warehouseId);
            })
            ->sum('qty');

        return $purchased - $sold;
    }

    /**
     * Get forecast stock (including pending) for a specific warehouse.
     */
    public function getForecastStock(int $warehouseId)
    {
        return $this->getRealStock($warehouseId);
    }
}
