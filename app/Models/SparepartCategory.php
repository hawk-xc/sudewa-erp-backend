<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SparepartCategory extends Model
{
    use HasFactory;

    protected $table = 'sparepart_categories';

    protected $fillable = [
        'uuid',
        'code',
        'name',
    ];

    public function spareparts()
    {
        return $this->hasMany(Sparepart::class);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
