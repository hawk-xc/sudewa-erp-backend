<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialTransaction extends Model
{
    use HasFactory;

    protected $table = 'material_transactions';

    protected $fillable = [
        'uuid',
        'warehouse_id',
        'person_id',
        'code',
        'type', // purchase,sales
        'stock_state',
        'is_refunded',
        'supplier_name',
        'is_paid',
        'transaction_date',
        'description',
    ];

    protected $appends = [
        'total_brutto',
    ];

    public function getTotalBruttoAttribute()
    {
        return $this->getTotalAmount();
    }

    protected $casts = [
        'warehouse_id' => 'integer',
        'person_id' => 'integer',
        'is_paid' => 'boolean',
        'is_refunded' => 'boolean',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function materialTransactionBillings()
    {
        return $this->hasMany(MaterialTransactionBilling::class);
    }

    public function materialTransactionDetails()
    {
        return $this->hasMany(MaterialTransactionDetail::class);
    }

    public function getTotalAmount(): int
    {
        return (int) $this->materialTransactionDetails->sum('total');
    }

    public function getTotalPaidAmount(): int
    {
        return (int) $this->materialTransactionBillings->sum('amount');
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
