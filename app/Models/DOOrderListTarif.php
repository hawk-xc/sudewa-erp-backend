<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOOrderListTarif extends Model
{
    use HasFactory;

    protected $table = 'do_order_list_tarifs';

    protected $fillable = [
        'uuid',
        'do_orderlist_id',
        'tarif_id',
        'qty',
        'load_content',
        'delivery_destination'
    ];

    protected $casts = [
        'do_orderlist_id' => 'integer',
        'tarif_id' => 'integer',
        'qty' => 'integer',
    ];

    public function do_order_list()
    {
        return $this->belongsTo(DOOrderList::class, 'do_orderlist_id');
    }

    public function tarif()
    {
        return $this->belongsTo(Tarif::class, 'tarif_id');
    }

    public function expeditions()
    {
        return $this->belongsToMany(
            DOExpedition::class,
            'do_expedition_order_list_tarifs',
            'do_order_list_tarif_id',
            'do_expedition_id'
        )->withTimestamps();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
