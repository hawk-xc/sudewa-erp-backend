<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UnitTransaction extends Model
{
    use HasFactory;

    protected $table = 'unit_transactions';

    protected $fillable = [
        'uuid',
        'warehouse_id',
        'person_id',
        'code',
        'type',
        'max_capacity',
        'stock_state',
        'invoice_file',
    ];

    protected $casts = [
        'max_capacity' => 'decimal:2',
        'stock_state' => 'string',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function transactionFlow()
    {
        return $this->hasOne(TransactionFlow::class);
    }

    public function unitTransactionBilling()
    {
        return $this->hasOne(UnitTransactionBilling::class);
    }

    public function unitTransactionItems()
    {
        return $this->hasMany(UnitTransactionItem::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function getBrutoAmount()
    {
        $unitTransactions = $this->unitTransactionItems();
        $total_dpp = $unitTransactions->sum('dpp_total_price');
        $total_ppn = $unitTransactions->sum('ppn_total_price');
        $bbn_price = $unitTransactions->sum('bbn_price');
        $other_fee = $unitTransactions->sum('other_fee');

        return $total_dpp + $total_ppn + $bbn_price + $other_fee;
    }

    public function getSumAmount(string $columnName): ?int
    {
        $validColumnName = [
            'dpp_total_price',
            'ppn_total_price',
            'bbn_price',
            'other_fee',
        ];

        if (! in_array($columnName, $validColumnName)) {
            return null;
        }

        $unitTransactions = $this->unitTransactionItems();

        return (int) $unitTransactions->sum((string) $columnName);
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
