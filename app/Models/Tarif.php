<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tarif extends Model
{
    use HasFactory;

    protected $table = 'tarifs';

    protected $fillable = [
        'uuid',
        'customer_id',
        'loading_in',
        'loading_out',
        'distance',
        'uj_towing',
        'uj_cdd',
        'uj_fuso',
        'inv_cdd',
        'inv_fuso',
        'is_active',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'distance' => 'integer',
        'uj_towing'=> 'integer',
        'uj_cdd'=> 'integer',
        'uj_fuso'=> 'integer',
        'inv_cdd'=> 'integer',
        'inv_fuso'=> 'integer',
        'is_active' => 'integer'
    ];

    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id', 'id');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
