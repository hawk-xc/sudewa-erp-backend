<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class BBNBillBillingItem extends Model
{
    use HasFactory;

    protected $table = 'bbn_bill_billing_items';

    protected $fillable = [
        'uuid',
        'bbn_bill_billing_id',
        'paid_date',
        'cash_id',
        'amount',
    ];

    protected $casts = [
        'bbn_bill_billing_id' => 'integer',
        'paid_date' => 'datetime',
        'cash_id' => 'integer',
        'amount' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function bbnBillBilling()
    {
        return $this->belongsTo(BBNBillBilling::class, 'bbn_bill_billing_id');
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class, 'cash_id');
    }
}
