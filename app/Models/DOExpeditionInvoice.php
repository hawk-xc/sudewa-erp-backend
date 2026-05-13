<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOExpeditionInvoice extends Model
{
    use HasFactory;

    protected $table = 'do_expedition_invoices';

    protected $fillable = [
        'uuid',
        'do_expedition_id',
        'qty',
        'do_letter_code',
        'do_assignment_code',
        'description',
        'is_already_print'
    ];

    protected $casts = [
        'do_expedition_id' => 'integer',
        'qty' => 'integer',
        'is_already_print' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function doExpedition()
    {
        return $this->belongsTo(DOExpedition::class);
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
