<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialTransactionDetail extends Model
{
    use HasFactory;

    protected $table = 'material_transaction_details';

    protected $fillable = [
        'uuid',
        'order_code',
        'material_transaction_id',
        'material_id',
        'in_stock', // bool -> default false
        'is_forecast', // bool -> default true 
        'qty',
        'price',
        'description',
    ];

    protected $casts = [
        'material_transaction_id' => 'integer',
        'material_id' => 'integer',
        'qty' => 'integer',
        'price' => 'integer',
        'in_stock' => 'boolean',
        'is_forecast' => 'boolean',
    ];

    protected $appends = [
        'total',
    ];

    public function getTotalAttribute()
    {
        return $this->price * $this->qty;
    }

    public function materialTransaction()
    {
        return $this->belongsTo(MaterialTransaction::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouseMovement()
    {
        return $this->hasOne(WarehouseMovement::class, 'material_transaction_detail_id', 'id');
    }

    public function receiptStock(?int $activityId = null)
    {
        $materialTransaction = $this->materialTransaction;

        return WarehouseMovement::firstOrCreate(
            [
                'material_transaction_detail_id' => $this->id,
                'status' => 'in',
            ],
            [
                'warehouse_activity_id' => $activityId,
                'material_transaction_id' => $materialTransaction->id,
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
