<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleEquipmentTransactionDetail extends Model
{
    use HasFactory;

    protected $table = 'vehicle_equipment_transaction_details';

    protected $fillable = [
        'uuid',
        'vehicle_equipment_id',
        'vehicle_equipment_transaction_id'
    ];

    public function vehicleEquipment()
    {
        return $this->belongsTo(VehicleEquipment::class, 'vehicle_equipment_id', 'id');
    }

    public function vehicleEquipmentTransaction()
    {
        return $this->belongsTo(VehicleEquipmentTransaction::class, 'vehicle_equipment_transaction_id', 'id');
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
