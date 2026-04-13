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
        'is_refunded'
    ];

    protected $casts = [
        'max_capacity' => 'decimal:2',
        'stock_state' => 'string',
        'is_refunded' => 'boolean'
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

    public function unitTransactionAdjustments()
    {
        return $this->hasMany(UnitTransactionAdjustment::class);
    }

    public function getBrutoAmount()
    {
        $unitTransactionItems = $this->unitTransactionItems();
        $total_dpp = $unitTransactionItems->sum('dpp_total_price');
        $total_ppn = $unitTransactionItems->sum('ppn_total_price');
        $bbn_price = $unitTransactionItems->sum('bbn_price');
        $other_fee = $unitTransactionItems->sum('other_fee');

        return $total_dpp + $total_ppn + $bbn_price + $other_fee;
    }

    public function getBrutoAmountActual()
    {
        return $this->unitTransactionItems()
            ->withCount(['unitTransactionItemDetails as actual_qty'])
            ->get()
            ->sum(function ($item) {

                if ($item->actual_qty == 0) {
                    return 0;
                }

                $dpp = $item->actual_qty * $item->dpp_per_unit_price;
                $ppn = $item->actual_qty * $item->ppn_per_unit_price;

                return $dpp + $ppn + ($item->bbn_price ?? 0) + ($item->other_fee ?? 0);
            });
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

    public function getSumAmountActual(string $columnName): ?int
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

        return (int) $this->unitTransactionItems()
            ->withCount(['unitTransactionItemDetails as actual_qty'])
            ->get()
            ->sum(function ($item) use ($columnName) {

                if ($item->actual_qty == 0) {
                    return 0;
                }

                return match ($columnName) {

                    'dpp_total_price' => $item->actual_qty * $item->dpp_per_unit_price,

                    'ppn_total_price' => $item->actual_qty * $item->ppn_per_unit_price,

                    'bbn_price' => $item->bbn_price ?? 0,

                    'other_fee' => $item->other_fee ?? 0,

                    default => 0,
                };
            });
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
