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
        'code',
        'type', // purchase,sales
        'supplier_name',
        'is_paid',
        'transaction_date',
        'description',
    ];

    protected $casts = [
        'is_paid' => 'boolean'
    ];

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
