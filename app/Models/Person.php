<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Person extends Model
{
    use HasFactory;

    protected $table = 'persons';

    protected $fillable = [
        'uuid',
        'company_id',
        'code',
        'type',
        'name',
        'address',
        'npwp',
        'phone',
        'pic_name',
        'identity_number',
        'drive_license_identity_number',
        'image',
        'map_link',
        'join_date',
        'social_media_1_link',
        'social_media_2_link',
        'social_media_3_link',
        'social_media_4_link',
        'website_link',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }

    public function unitTransactions()
    {
        return $this->hasMany(UnitTransaction::class);
    }

    public function ownershipTransferFees()
    {
        return $this->hasMany(OwnershipTransferFee::class);
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
