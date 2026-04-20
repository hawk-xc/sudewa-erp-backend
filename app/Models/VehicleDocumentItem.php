<?php

namespace App\Models;

use App\Models\VehicleDocument;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleDocumentItem extends Model
{
    use HasFactory;

    protected $table = 'vehicle_document_items';

    protected $fillable = [
        'uuid',
        'vehicle_document_id',
        'vendor_id',
        'vehicle_data_id',

        // BPKB
        'bpkb_registration_date', // date
        'bpkb_received_date', // date
        'bpkb_physical_status', // bool
        'bpkb_number', // string

        // STNK
        'stnk_registration_date', // date
        'stnk_received_date', // date
        'stnk_physical_status', // bool

        // SKPD
        'skpd_payment_date', // date
        'skpd_received_date', // date
        'skpd_physical_status', // bool

        // TNKB
        'tnkb_received_date', // date
        'tnkb_number', // string
        'tnkb_physical_status', // bool

        // Administration Costs
        'stck_fee', // decimal(15,2)
        'bbn_registration_fee', // decimal(15,2)
        'notice_fee', // decimal(15,2)
        'pmi_fee', // decimal(15,2)

        'physical_check_fee', // decimal(15,2)
        'nik_validation_fee', // decimal(15,2)
        'garwil_fee', // decimal(15,2)
        'built_up_fee', // decimal(15,2)

        'acceleration_fee', // decimal(15,2)
        'plate_recommendation_fee', // decimal(15,2)
        'service_fee', // decimal(15,2)
        'skpd_fee', // decimal(15,2)
    ];

    public function vehicleDocument()
    {
        return $this->belongsTo(VehicleDocument::class);
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
