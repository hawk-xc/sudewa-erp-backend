<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Str;

class BBNBill extends Model
{
    use HasFactory;

    protected $table = 'bbn_bills';

    protected $fillable = [
        'uuid',
        'dealer_id',
        'bill_date',
        'paid_date',
    ];

    protected $casts = [
        'dealer_id' => 'integer',
        'bill_date' => 'datetime',
        'paid_date' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function dealer()
    {
        return $this->belongsTo(Person::class, 'dealer_id');
    }

    public function bbnBillBillings()
    {
        return $this->hasMany(BBNBillBilling::class, 'bbn_bill_id');
    }
}
