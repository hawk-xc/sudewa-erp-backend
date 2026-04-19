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
        'transaction_date',
        'description',
    ];

    public function materialTransactionBillings()
    {
        return $this->hasMany(MaterialTransactionBilling::class);
    }

    public function materialTransactionDetails()
    {
        return $this->hasMany(MaterialTransactionDetail::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
