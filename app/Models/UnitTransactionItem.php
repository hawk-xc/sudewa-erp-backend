<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransactionItem extends Model
{
    use HasFactory;

    protected $table = 'unit_transaction_items';

    protected $fillable = [
        'uuid',
        'unit_transaction_id',
        'unit_type_id',
        'sparepart_id',

        'dpp_tax_id',
        'dpp_tax_rate',
        'ppn_tax_id',
        'ppn_tax_rate',

        'qty_total',
        'price',
        'price_per_unit_usd',
        'price_usd',
        'bbn_price',
        'expedition_fee',
        'other_fee',
        'hpp_per_unit_price',
        'dpp_per_unit_price',
        'ppn_per_unit_price',
        'hpp_total_price',
        'dpp_total_price',
        'ppn_total_price',
    ];

    protected $casts = [
        'dpp_tax_id' => 'integer',
        'dpp_tax_rate' => 'float',
        'ppn_tax_id' => 'integer',
        'ppn_tax_rate' => 'float',
        'transaction_date' => 'date',
        'quantity' => 'integer',
        'price' => 'integer',
        'price_per_unit_usd' => 'float',
        'price_usd' => 'float',
        'total_price' => 'integer',
        'bbn_price' => 'integer',
        'expedition_fee' => 'integer',
        'other_fee' => 'integer',
        'hpp_per_unit_price' => 'integer',
        'dpp_per_unit_price' => 'integer',
        'ppn_per_unit_price' => 'integer',
        'hpp_total_price' => 'integer',
        'dpp_total_price' => 'integer',
        'ppn_total_price' => 'integer',
    ];

    public function unitTransaction()
    {
        return $this->belongsTo(UnitTransaction::class);
    }

    public function unitType()
    {
        return $this->belongsTo(UnitType::class);
    }

    public function sparepart()
    {
        return $this->belongsTo(Sparepart::class);
    }

    public function dppTax()
    {
        return $this->belongsTo(TaxVersion::class, 'dpp_tax_id');
    }

    public function ppnTax()
    {
        return $this->belongsTo(TaxVersion::class, 'ppn_tax_id');
    }

    public function unitTransactionItemDetails()
    {
        return $this->hasMany(UnitTransactionItemDetail::class);
    }

    public function unitTransactionItemSales()
    {
        return $this->hasMany(UnitTransactionItemSales::class);
    }

    public function unitTransactionAdjustmentItems()
    {
        return $this->hasMany(UnitTransactionAdjustmentItems::class);
    }

    public function unitTypeSoldDetails()
    {
        return $this->belongsToMany(
            UnitTransactionItemDetail::class,
            'unit_transaction_item_sales',
            'unit_transaction_item_id',
            'unit_transaction_item_detail_id'
        );
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
