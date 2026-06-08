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
        'code',
        'ditlantas_process_id',
        'bill_date',
        'paid_date',
    ];

    protected $casts = [
        'ditlantas_process_id' => 'integer',
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
        'remaining_amount',
    ];

    public function getBruttoAmountAttribute()
    {
        $ditlantasProcessId = $this->ditlantas_process_id;
        if (!$ditlantasProcessId) return 0;

        $subTotal = (int) VehicleRegistration::where('ditlantas_process_id', $ditlantasProcessId)
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
        $ditlantasProcessId = $this->ditlantas_process_id;
        if (!$ditlantasProcessId) return 0;

        $subTotal = (int) VehicleRegistration::where('ditlantas_process_id', $ditlantasProcessId)
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

    public function getRemainingAmountAttribute()
    {
        return (int) ($this->brutto_amount - $this->paid_amount);
    }

    public function ditlantasProcess()
    {
        return $this->belongsTo(DitlantasProcess::class, 'ditlantas_process_id');
    }

    public function bbnBillBillings()
    {
        return $this->hasMany(BBNBillBilling::class, 'bbn_bill_id');
    }
}
