<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionBillingHistory extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_billing_histories';

    protected $fillable = [
        'uuid',
        'unit_transaction_billing_id',
        'payment_proof',
        'payment_at',
        'note',
    ];

    protected $casts = [
        'payment_at' => 'date',
    ];

    public function unitTransactionBilling()
    {
        return $this->belongsTo(UnitTransactionBilling::class, 'unit_transaction_billing_id', 'id');
    }

    public function cashFlow()
    {
        return $this->hasOne(CashFlow::class, 'unit_transaction_billing_history_id');
    }

    public function cashes()
    {
        return $this->belongsToMany(Cash::class, 'cash_unit_transaction_billing_history', 'unit_transaction_billing_history_id', 'cash_id')
            ->using(CashUnitTransactionBillingHistory::class)
            ->withPivot(['amount', 'original_amount', 'exchange_amount'])
            ->withTimestamps();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
