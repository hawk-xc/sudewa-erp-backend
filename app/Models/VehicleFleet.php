<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleFleet extends Model
{
    use HasFactory;

    protected $table = 'vehicle_fleets';

    protected $fillable = [
        'uuid',
        'registration_number',
        'type',
        'machine_number',
        'chassis_number',
        'stnk_age', 
        'kir_age',
        'stnk_number',
        'kir_book'
    ];

    public function vehicleFleetEquipment()
    {
        return $this->hasOne(VehicleFleetEquipment::class);
    }

    public function goodsTransactions()
    {
        return $this->hasMany(GoodsTransaction::class, 'vehicle_fleet_id', 'id');
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
