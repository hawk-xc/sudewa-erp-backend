<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class BBNBillBilling extends Model
{
    use HasFactory;

    protected $table = 'bbn_bill_billings';

    protected $fillable = [
        'uuid',
        'bbn_bill_id',
        'total_payment',
    ];

    protected $casts = [
        'bbn_bill_id' => 'integer',
        'total_payment' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function bbnBill()
    {
        return $this->belongsTo(BBNBill::class, 'bbn_bill_id');
    }

    public function bbnBillBillingItems()
    {
        return $this->hasMany(BBNBillBillingItem::class, 'bbn_bill_billing_id');
    }

    public function getPaidAmount()
    {
        return $this->bbnBillBillingItems()->sum('amount');
    }

    public function getRemainingAmount()
    {
        return (int) $this->total_payment - (int) $this->getPaidAmount();
    }
}
