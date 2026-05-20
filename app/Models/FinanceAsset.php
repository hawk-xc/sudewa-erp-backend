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
        'economic_age',
        'description' // text
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'economic_age' => 'integer',
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
