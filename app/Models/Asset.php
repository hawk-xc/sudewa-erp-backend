<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    protected $fillable = [
        'uuid',
        'company_id',
        'code',
        'name',
        'type', // inventory, vehicles, buildings, land
    ];

    protected $casts = [
        'company_id' => 'integer',
    ];

    protected $appends = [
        'serial_number',
        'purchase_date',
        'price',
    ];

    public function getSerialNumberAttribute()
    {
        return $this->financeAsset?->serial_number;
    }

    public function getPurchaseDateAttribute()
    {
        $date = $this->financeAsset?->purchase_date;
        return $date instanceof \Carbon\Carbon ? $date->format('Y-m-d') : $date;
    }

    public function getPriceAttribute()
    {
        return $this->financeAsset?->price;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function financeAsset()
    {
        return $this->hasOne(FinanceAsset::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
