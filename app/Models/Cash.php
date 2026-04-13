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

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
