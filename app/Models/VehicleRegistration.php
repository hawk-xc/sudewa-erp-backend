<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleRegistration extends Model
{
    use HasFactory;

    protected $table = 'vehicle_registrations';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'vehicle_data_id',
        'process_date',  // date
        'is_already_processed' // bool -> default false
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'vehicle_data_id' => 'integer',
    ];

    public function vendor()
    {
        return $this->belongsTo(Person::class);
    }

    public function vehicleData()
    {
        return $this->belongsTo(VehicleData::class);
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
