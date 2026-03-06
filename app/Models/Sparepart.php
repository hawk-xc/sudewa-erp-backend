<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sparepart extends Model
{
    use HasFactory;

    protected $table = 'spareparts';

    protected $fillable = [
        'uuid',
        'sparepart_category_id',
        'code',
        'name',
        'price',
        'capacity',
        'image',
        'unit_type',
    ];

    public function sparepartCategory()
    {
        return $this->belongsTo(SparepartCategory::class);
    }

    public function unitTransactionItems()
    {
        return $this->hasMany(UnitTransactionItem::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
