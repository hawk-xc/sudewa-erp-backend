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
        'price',
        'asset_id',
        'purchase_date',
        'economic_age',
        'description',
        'serial_number',
    ];

    protected $casts = [
        'price' => 'integer',
        'asset_id' => 'integer',
        'economic_age' => 'integer',
        'purchase_date' => 'date',
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
