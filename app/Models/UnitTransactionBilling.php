<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionBilling extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_billings';

    protected $fillable = [
        'uuid',
        'unit_transaction_id',
        'grand_total',
        'last_payment_at',
        'is_paid',
    ];

    protected $casts = [
        'unit_transaction_id' => 'integer',
        'last_payment_at' => 'date',
        'grand_total' => 'integer',
        'is_paid' => 'boolean',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    public function financeBilling()
    {
        return $this->hasOne(FinanceBilling::class, 'unit_transaction_billing_id', 'id');
    }

    public function unitTransactionBillingHistories()
    {
        return $this->hasMany(UnitTransactionBillingHistory::class);
    }

    public function cashFlow()
    {
        return $this->hasOne(CashFlow::class,'unit_transaction_billing_id', 'id');
    }

    public function getTotalCashPayment(): int
    {
        return (int) \DB::table('cash_unit_transaction_billing_history')
            ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
            ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
            ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $this->id)
            ->where('cashes.code', 'cash_idr')
            ->sum('cash_unit_transaction_billing_history.amount');
    }

    public function getTotalBcaCashPayment(): int
    {
        return (int) \DB::table('cash_unit_transaction_billing_history')
            ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
            ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
            ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $this->id)
            ->where('cashes.code', 'bca_idr')
            ->sum('cash_unit_transaction_billing_history.amount');
    }

    public function getTotalBcaUsdPayment(): int
    {
        return (int) \DB::table('cash_unit_transaction_billing_history')
            ->join('unit_transaction_billing_histories', 'unit_transaction_billing_histories.id', '=', 'cash_unit_transaction_billing_history.unit_transaction_billing_history_id')
            ->join('cashes', 'cashes.id', '=', 'cash_unit_transaction_billing_history.cash_id')
            ->where('unit_transaction_billing_histories.unit_transaction_billing_id', $this->id)
            ->where('cashes.code', 'bca_usd')
            ->sum('cash_unit_transaction_billing_history.amount');
    }

    public function getTotalPaid(): int
    {
        return (int) (
            $this->getTotalCashPayment() +
            $this->getTotalBcaCashPayment()
        );
    }

    public function getRemainingPayment(): int
    {
        return (int) ($this->grand_total - $this->getTotalPaid());
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
