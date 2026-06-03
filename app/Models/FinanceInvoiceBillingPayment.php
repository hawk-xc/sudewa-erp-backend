<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceInvoiceBillingPayment extends Model
{
    use HasFactory;

    protected $table = 'finance_invoice_billing_payments';

    protected $fillable = [
        'uuid',
        'do_invoice_id',
        'cash_id',
        'amount'
    ];

    protected $casts = [
        'do_invoice_id' => 'integer',
        'cash_id' => 'integer',
        'amount' => 'integer'
    ];

    public function doInvoice()
    {
        return $this->belongsTo(DOInvoice::class, 'do_invoice_id', 'id');
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class, 'cash_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
