<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleEquipmentTransaction extends Model
{
    use HasFactory;

    protected $table = 'vehicle_equipment_transactions';

    protected $fillable = [
        'uuid',
        'code',
        'person_id', // nullable
        'vehicle_fleet_id', // nullable
        'supplier_name', // nullable
        'type', // receipt, dispatch
        'transaction_date',
        'purchase_amount',
        'location',
        'category',
        'description'
    ];

    public function VehicleEquipmentTransactionDetails()
    {
        return $this->hasMany(VehicleEquipmentTransactionDetail::class);
    }

    public function driver()
    {
        return $this->belongsTo(Person::class, 'person_id', 'id');
    }

    public function vehicleFleet()
    {
        return $this->belongsTo(VehicleFleet::class, 'vehicle_fleet_id', 'id');
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
