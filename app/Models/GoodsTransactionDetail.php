<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoodsTransactionDetail extends Model
{
    use HasFactory;

    protected $table = 'goods_transaction_details';

    protected $fillable = [
        'uuid',
        'goods_transaction_id',
        'material_id',
        'vehicle_equipment_id',
        'in_stock', // bool -> default false
        'is_forecast', // bool -> default true 
        'qty',
        'type', // pcs, set, box
        'price',
        'description',
    ];

    protected $casts = [
        'goods_transaction_id' => 'integer',
        'material_id' => 'integer',
        'vehicle_equipment_id' => 'integer',
        'qty' => 'integer',
        'price' => 'integer',
        'in_stock' => 'boolean',
        'is_forecast' => 'boolean',
    ];

    protected $appends = [
        'total',
    ];

    public function vehicleEquipment()
    {
        return $this->belongsTo(VehicleEquipment::class, 'vehicle_equipment_id', 'id');
    }

    public function getTotalAttribute()
    {
        return $this->price * $this->qty;
    }

    public function goodsTransaction()
    {
        return $this->belongsTo(GoodsTransaction::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouseMovement()
    {
        return $this->hasOne(WarehouseMovement::class, 'goods_transaction_detail_id', 'id');
    }

    public function receiptStock(?int $activityId = null)
    {
        $goodsTransaction = $this->goodsTransaction;

        return WarehouseMovement::firstOrCreate(
            [
                'goods_transaction_detail_id' => $this->id,
                'status' => 'in',
            ],
            [
                'warehouse_activity_id' => $activityId,
                'goods_transaction_id' => $goodsTransaction->id,
                'status' => 'in',
            ]
        );
    }

    public function dispatchStock()
    {
        $movement = $this->warehouseMovement()->first();

        if (! $movement) {
            throw new \Exception('Material not found in warehouse');
        }

        $movement->update([
            'status' => 'out',
        ]);

        $this->update([
            'in_stock' => false,
        ]);

        return $movement;
    }

    public function refundStock()
    {
        $this->update([
            'is_forecast' => false,
            'in_stock' => false,
        ]);

        $movement = $this->warehouseMovement()->where('status', 'in')->first();
        if ($movement) {
            $movement->update(['status' => 'out']);
        }

        return $this;
    }

    public function returnStock()
    {
        $this->update([
            'is_forecast' => false,
            'in_stock' => false,
        ]);

        $movement = $this->warehouseMovement()->where('status', 'in')->first();
        if ($movement) {
            $movement->update(['status' => 'out']);
        }

        return $this;
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
