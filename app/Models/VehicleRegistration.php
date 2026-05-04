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
        
        // New Registration Column
        'customer_delivery_date',
        
        'bpkb_number',
        'bpkb_registration_date',
        'bpkb_received_date',
        'bpkb_physical_status',

        // STNK
        'stnk_registration_date',
        'stnk_received_date',
        'stnk_physical_status',

        // SKPD
        'skpd_payment_date',
        'skpd_received_date',
        'skpd_physical_status',

        // TNKB
        'tnkb_received_date',
        'tnkb_number',
        'tnkb_physical_status',

        // Administration Costs
        'stck_fee',
        'bbn_registration_fee',
        'notice_fee',
        'pmi_fee',

        'physical_check_fee',
        'nik_validation_fee',
        'garwil_fee',
        'built_up_fee',

        'acceleration_fee',
        'plate_recommendation_fee',
        'service_fee',
        'skpd_fee',

        // processed mark
        'is_already_processed'
    ];

    protected $casts = [
        'vendor_id' => 'integer',
        'vehicle_data_id' => 'integer',
        'is_already_processed' => 'boolean',
        'bpkb_physical_status' => 'boolean',
        'stnk_physical_status' => 'boolean',
        'skpd_physical_status' => 'boolean',
        'tnkb_physical_status' => 'boolean',
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
