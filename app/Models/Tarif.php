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
        'loading_in',
        'loading_out',
        'distance',
        'uj_towing',
        'uj_cdd',
        'uj_fuso',
        'inv_cdd',
        'inv_fuso',
        'inv_towing',
        'is_active',
    ];

    protected $casts = [
        'distance' => 'integer',
        'uj_towing'=> 'integer',
        'uj_cdd'=> 'integer',
        'uj_fuso'=> 'integer',
        'inv_cdd'=> 'integer',
        'inv_fuso'=> 'integer',
        'inv_towing'=> 'integer',
        'is_active' => 'integer'
    ];

    public function DOOrderListTarifs()
    {
        return $this->hasMany(DOOrderListTarif::class);
    }

    public function DOOrderLists()
    {
        return $this->belongsToMany(
            DOOrderList::class, 'do_order_list_tarifs', 'tarif_id', 'do_order_list_id')
        ->withPivot(
            'uuid',
            'qty',
            'vehicle_type',
            'load_content'
        )->withTimestamps();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
