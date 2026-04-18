<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FinanceAsset extends Model
{
    use HasFactory;

    protected $table = 'finance_assets';

    protected $fillable = [
        'uuid',
        'asset_id',
        'serial_number', // varchar
        'economic_age', // year
        'depreciation', // decimal(15,2)
        'residual_value', // decimal(15,2)
        'final_value', // decimal(15,2)
        'description' // text
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
