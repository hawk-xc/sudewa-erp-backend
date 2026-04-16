<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceBilling extends Model
{
    use HasFactory;
    
    protected $table = 'finance_billings';

     protected $fillable = [
        'uuid',
        'unit_transaction_billing_id',
        'last_payment_at',
        'grand_total',
        'is_valid'
    ];

    protected $casts = [
        'unit_transaction_billing_id' => 'integer',
        'last_payment_at' => 'date',
        'is_valid' => 'boolean'
    ];

    public function unitTransactionBilling()
    {
        return $this->belongsTo(UnitTransactionBilling::class, 'unit_transaction_billing_id', 'id');
    }

    public function financeBillingItems()
    {
        return $this->hasMany(FinanceBillingItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
