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

    protected $casts = [
        'receipt_date' => 'date',
    ];

    public function vendor()
    {
        return $this->belongsTo(Person::class);
    }

    public function vehicleDocumentItems()
    {
        return $this->hasMany(VehicleDocumentItem::class);
    }

    public function vehicleRegistrations()
    {
        return $this->hasManyThrough(
            VehicleRegistration::class,
            VehicleDocumentItem::class,
            'vehicle_document_id',
            'vehicle_data_id',
            'id',
            'vehicle_data_id'
        );
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
