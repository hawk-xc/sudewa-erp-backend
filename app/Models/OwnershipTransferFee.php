<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OwnershipTransferFee extends Model
{
    use HasFactory;

    protected $table = 'ownership_transfer_fees';

    protected $fillable = [
        'uuid',
        'dealer_id',
        'region_id',
        'tnbk_code',
        'vehicle_type',
        'un_notice_fee',
        'garwil_fee',
        'countershop_fee',
        'other_fee',
    ];

    public function dealer()
    {
        return $this->belongsTo(Person::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
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
