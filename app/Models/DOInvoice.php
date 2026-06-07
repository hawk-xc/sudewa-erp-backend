<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DOInvoice extends Model
{
    use HasFactory;

    protected $table = 'do_invoices';

    protected $fillable = [
        'uuid',
        'code',
        'customer_id',
        'do_order_list_id',
        'date',
        'subject',
        'letter_content',
        'description',
        'other_fee',
        'additional_fee',
        'is_already_print'
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'do_order_list_id' => 'integer',
        'date' => 'date',
        'is_already_print' => 'boolean',
        'other_fee' => 'integer',
        'additional_fee' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function customer()
    {
        return $this->belongsTo(Person::class, 'customer_id');
    }

    public function order_list()
    {
        return $this->belongsTo(DOOrderList::class, 'do_order_list_id');
    }

    public function financeBillingPayment()
    {
        return $this->hasOne(FinanceInvoiceBillingPayment::class, 'do_invoice_id', 'id');
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
