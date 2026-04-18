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
        'purchase_date',
        'name',
        'type', // inventory, vehicles, buildings, land
        'price'
    ];

    protected $casts = [
        'company_id' => 'integer',
        'price' => 'integer'  
    ];

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
