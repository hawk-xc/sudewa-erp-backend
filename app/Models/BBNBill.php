<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class BBNBill extends Model
{
    use HasFactory;

    protected $table = 'bbn_bills';

    protected $fillable = [
        'uuid',
        'dealer_id',
        'bill_date',
        'paid_date',
    ];

    protected $casts = [
        'dealer_id' => 'integer',
        'bill_date' => 'datetime',
        'paid_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    protected $appends = [
        'brutto_amount',
        'paid_amount',
        'is_paid',
        'pph23_amount',
    ];

    public function getBruttoAmountAttribute()
    {
        $dealer = $this->dealer;
        if (!$dealer) return 0;

        $vehicleDataIds = $dealer->vehicleDatas()->pluck('id');

        $subTotal = (int) VehicleRegistration::whereIn('vehicle_data_id', $vehicleDataIds)
            ->get()
            ->sum(function ($reg) {
                return $reg->stck_fee +
                       $reg->bbn_registration_fee +
                       $reg->notice_fee +
                       $reg->pmi_fee +
                       $reg->physical_check_fee +
                       $reg->nik_validation_fee +
                       $reg->garwil_fee +
                       $reg->built_up_fee +
                       $reg->acceleration_fee +
                       $reg->plate_recommendation_fee +
                       $reg->service_fee +
                       $reg->skpd_fee +
                       $reg->stamp_fee +
                       $reg->pnbp_bpkb;
            });

        // Add PPh 23 (2%)
        $pph23 = $subTotal * 0.02;
        return (int) ($subTotal + $pph23);
    }

    public function getPPH23AmountAttribute()
    {
        $dealer = $this->dealer;
        if (!$dealer) return 0;

        $vehicleDataIds = $dealer->vehicleDatas()->pluck('id');

        $subTotal = (int) VehicleRegistration::whereIn('vehicle_data_id', $vehicleDataIds)
            ->get()
            ->sum(function ($reg) {
                return $reg->stck_fee +
                       $reg->bbn_registration_fee +
                       $reg->notice_fee +
                       $reg->pmi_fee +
                       $reg->physical_check_fee +
                       $reg->nik_validation_fee +
                       $reg->garwil_fee +
                       $reg->built_up_fee +
                       $reg->acceleration_fee +
                       $reg->plate_recommendation_fee +
                       $reg->service_fee +
                       $reg->skpd_fee +
                       $reg->stamp_fee +
                       $reg->pnbp_bpkb;
            });

        // Add PPh 23 (2%)
        $pph23 = $subTotal * 0.02;
        return (int) $pph23;
    }

    public function getPaidAmountAttribute()
    {
        return (int) $this->bbnBillBillings()->sum('total_payment');
    }

    public function getIsPaidAttribute()
    {
        if ($this->brutto_amount <= 0) return false;
        return $this->paid_amount >= $this->brutto_amount;
    }

    public function dealer()
    {
        return $this->belongsTo(Person::class, 'dealer_id');
    }

    public function bbnBillBillings()
    {
        return $this->hasMany(BBNBillBilling::class, 'bbn_bill_id');
    }
}
