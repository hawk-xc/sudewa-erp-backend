<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MaterialTransactionBilling extends Model
{
    use HasFactory;

    protected $table = 'material_transaction_billings';

    protected $fillable = [
        'uuid',
        'material_transaction_id',
        'cash_id', 
        'amount', // decimal(15,2)
        'is_paid',
        'payment_date',
        'description',
    ];

    protected $casts = [
        'material_transaction_id' => 'integer',
        'cash_id' => 'integer',
        'is_paid' => 'boolean',
        'amount' => 'integer',
    ];

    public function materialTransaction()
    {
        return $this->belongsTo(MaterialTransaction::class);
    }

    public function cash()
    {
        return $this->belongsTo(Cash::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
