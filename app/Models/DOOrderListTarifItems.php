<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOOrderListTarifItems extends Model
{
    use HasFactory;
    protected $table = 'd_o_order_list_tarif_load_items';

    protected $fillable = [
        'id',
        'uuid',
        'do_order_list_tarif_id',
        'load_content', 
        'qty'
    ];

    protected $casts = [
        'do_order_list_tarif_id' => 'integer',
        'qty' => 'integer'
    ];

    public function doOrderListTarif()
    {
        return $this->belongsTo(DOOrderListTarif::class);
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
