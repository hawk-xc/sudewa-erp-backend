<?php

namespace App\Models;

use App\Models\VehicleDocumentItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VehicleDocument extends Model
{
    use HasFactory;

    protected $table = 'vehicle_documents';

    protected $fillable = [
        'uuid',
        'code', // TRM-260419001 (YYMMDDXXX)
        'vendor_id',
        'receipt_date', // date
        'description'
    ];

    public function vendor()
    {
        return $this->belongsTo(Person::class);
    }

    public function vehicleDocumentItems()
    {
        return $this->hasMany(VehicleDocumentItem::class);
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
