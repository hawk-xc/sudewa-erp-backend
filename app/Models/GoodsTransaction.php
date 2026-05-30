<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoodsTransaction extends Model
{
    use HasFactory;

    protected $table = 'goods_transactions';

    protected $fillable = [
        'uuid',
        'code',
        'company_id',
        'supplier_id',
        'customer_id',
        'driver_id',
        'vehicle_fleet_id',
        'category', // maintenance, equipped
        'type', // receipt, issue
        'transaction_date',
        'location',
        'description',
        'invoice_file',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'supplier_id' => 'integer',
        'customer_id' => 'integer',
        'driver_id' => 'integer',
        'vehicle_fleet_id' => 'integer',
        'transaction_date' => 'date',
    ];

    protected $appends = [
        'total_brutto',
    ];

    public function getTotalBruttoAttribute()
    {
        return $this->getTotalAmount();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Person::class, 'supplier_id');
    }

    public function driver()
    {
        return $this->belongsTo(Person::class, 'driver_id');
    }

    public function vehicleFleet()
    {
        return $this->belongsTo(VehicleFleet::class);
    }

    public function goodsTransactionBillings()
    {
        return $this->hasOne(GoodsTransactionBilling::class);
    }

    public function goodsTransactionDetails()
    {
        return $this->hasMany(GoodsTransactionDetail::class);
    }

    public function goodsTransactionBillingPayments()
    {
        return $this->hasManyThrough(GoodsTransactionBillingPayment::class, GoodsTransactionBilling::class);
    }

    public function getTotalAmount(): int
    {
        return (int) $this->goodsTransactionDetails->sum('total');
    }

    public function getTotalPaidAmount(): int
    {
        return (int) $this->goodsTransactionBillingPayments()->sum('amount');
    }

    public function getRemainingAmount(): int
    {
        return $this->getTotalAmount() - $this->getTotalPaidAmount();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
