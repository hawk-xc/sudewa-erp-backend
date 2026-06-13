<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DitlantasProcess extends Model
{
    use HasFactory;

    protected $table = 'ditlantas_processed';

    protected $fillable = [
        'uuid',
        'code',
        'vendor_id',
        'process_date',
        'note'
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'process_date' => 'date'
    ];

    public function vehicleDatas() {
        return $this->belongsToMany(VehicleData::class, 'vehicle_data_ditlantas_processed', 'ditlantas_process_id', 'vehicle_data_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Person::class, 'vendor_id', 'id');
    }

    public function vehicleRegistrations()
    {
        return $this->hasMany(VehicleRegistration::class, 'ditlantas_process_id', 'id');
    }

    public function vehicleDocument()
    {
        return $this->hasOne(VehicleDocument::class, 'ditlantas_process_id', 'id');
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
