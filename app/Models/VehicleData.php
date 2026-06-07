<?php

namespace App\Models;

use App\Models\VehicleRegistration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleData extends Model
{
    use HasFactory;

    protected $table = 'vehicle_datas';

    protected $fillable = [
        'uuid',
        'dealer_id',
        'region_id',
        'invoice_number',
        'invoice_date',
        'invoice_receive_date',
        'vehicle_type',
        
        // customer data
        'ktp_number',
        'phone_number',
        'occupation',
        'stnk_name',
        'stnk_address',
        'village',
        'district',
        'sub_village',
        'sub_district',
        'regency',
        'postal_code',

        // vehicle data
        'motorcycle_brand',
        'motorcycle_type',
        'motorcycle_category',
        'motorcycle_model',
        'manufacture_year',
        'engine_capacity',
        'color',
        'price',
        'chassis_number',
        'machine_number',
        'form_ab',
        'pib',
        'tpt_number',
        'sut_number',
        'srut_number',
        'fuel_type'
    ];

    protected $casts = [
        // UUID
        'uuid' => 'string',

        // Foreign key
        'dealer_id' => 'integer',
        'region_id' => 'integer',

        // Invoice
        'invoice_number' => 'string',
        'invoice_date' => 'date',
        'invoice_receive_date' => 'date',
        'vehicle_type' => 'string',

        // Customer data
        'ktp_number' => 'string',
        'phone_number' => 'string',
        'occupation' => 'string',
        'stnk_name' => 'string',
        'stnk_address' => 'string',
        'village' => 'string',
        'district' => 'string',
        'sub_village' => 'string',
        'sub_district' => 'string',
        'regency' => 'string',
        'postal_code' => 'string',

        // Vehicle data
        'motorcycle_brand' => 'string',
        'motorcycle_type' => 'string',
        'motorcycle_category' => 'string',
        'motorcycle_model' => 'string',
        'manufacture_year' => 'integer',
        'engine_capacity' => 'integer',
        'color' => 'string',
        'price' => 'integer',
        'chassis_number' => 'string',
        'machine_number' => 'string',
        'form_ab' => 'string',
        'pib' => 'string',
        'tpt_number' => 'string',
        'sut_number' => 'string',
        'srut_number' => 'string',
        'fuel_type' => 'string',
    ];

    public function dealer()
    {
        return $this->belongsTo(Person::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function vehicleRegistration()
    {
        return $this->hasOne(VehicleRegistration::class);
    }

    public function ditlantasCoded()
    {
        return $this->belongsToMany(DitlantasProcess::class, 'vehicle_data_ditlantas_processed', 'vehicle_data_id', 'ditlantas_process_id')->one();
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
