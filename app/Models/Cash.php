<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Cash extends Model
{
    use HasFactory;

    protected $table = 'cashes';

    protected $fillable = [
        'uuid',
        'company_id',
        'account_id',
        'code',
        'description',
        'amount',
        'type',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class);
    }

    public function unitTransactionAdjustments()
    {
        return $this->hasMany(UnitTransactionAdjustment::class);
    }

    public function warehouseActivities()
    {
        return $this->hasMany(WarehouseActivity::class, 'cash_id', 'id');
    }

    public function unitTransactionBillingHistories()
    {
        return $this->belongsToMany(UnitTransactionBillingHistory::class, 'cash_unit_transaction_billing_history', 'cash_id', 'unit_transaction_billing_history_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function addAmount(int $amount)
    {
        $this->increment('amount', $amount);
    }

    public function subtractAmount(int $amount)
    {
        $this->decrement('amount', $amount);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
